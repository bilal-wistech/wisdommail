<?php

namespace Acelle\Console\Commands;

use Exception;
use Request;
use Carbon\Carbon;
use Acelle\Model\Field;
use Acelle\Model\MailList;
use Acelle\Model\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Client\ConnectionException;

class SyncMailLists extends Command
{
    protected $signature = 'sync:maillists';
    protected $description = 'Sync mail lists and subscribers with external API';

    private const API_ENDPOINT = 'https://britishchamberdubai.com/api/list-names';
    private const BATCH_SIZE = 100; // For batch processing

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            Log::info('Starting mail lists sync process');

            $apiData = $this->fetchApiDataWithRetry();

            if (empty($apiData)) {
                throw new Exception('Failed to retrieve valid API data');
            }

            DB::transaction(function () use ($apiData) {
                $this->syncMailLists($apiData);

                $mailLists = MailList::all();
                foreach ($mailLists as $mailList) {
                    $this->syncSubscribers($mailList, $apiData);
                }
            });

            Log::info('Mail lists sync completed successfully');
            $this->info('Sync completed successfully');
        } catch (Exception $e) {
            Log::error('Error in sync process: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Sync failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function fetchApiDataWithRetry($maxAttempts = 3)
    {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                $response = Http::timeout(30)
                    ->retry(2, 100)
                    ->get(self::API_ENDPOINT);

                if ($response->successful()) {
                    $data = $response->json();
                    if ($this->validateApiData($data)) {
                        return $data;
                    }
                    throw new Exception('Invalid API data structure');
                }

                throw new Exception('API request failed with status: ' . $response->status());
            } catch (ConnectionException $e) {
                Log::warning('API connection attempt ' . ($attempt + 1) . ' failed: ' . $e->getMessage());
                $attempt++;
                if ($attempt === $maxAttempts) {
                    throw new Exception('Failed to connect to API after ' . $maxAttempts . ' attempts');
                }
                sleep(pow(2, $attempt)); // Exponential backoff
            }
        }

        return null;
    }

    private function validateApiData($data)
    {
        return isset($data['lists']) && is_array($data['lists']);
    }

    private function syncMailLists(array $apiData)
    {
        foreach ($apiData['lists'] as $data) {
            try {
                $this->upsertMailList($data);
            } catch (Exception $e) {
                Log::error('Error syncing mail list ' . $data['title'] . ': ' . $e->getMessage());
                throw $e;
            }
        }
    }

    private function upsertMailList(array $data)
    {
        $mailList = MailList::firstOrNew(['name' => $data['title']]);

        if (!$mailList->exists) {
            $mailList->fill([
                'uid' => (string) Str::uuid(),
                'customer_id' => 1,
                'contact_id' => $this->getContact($data),
                'subscribe_confirmation' => 0,
                'unsubscribe_notification' => 0,
                'send_welcome_email' => 0,
                'from_email' => env('MAIL_FROM_ADDRESS'),
                'from_name' => 'BILAL',
            ]);
        }

        $mailList->save();
        return $mailList;
    }

    private function syncSubscribers(MailList $mailList, array $apiData)
    {
        $methodMap = [
            'Prospect List' => 'syncProspectSubscribers',
            'News Letter Subscription' => 'syncNewsletterSubscribers',
            'All Members' => 'syncMemberSubscribers',
            'Events List' => 'syncEventSubscribers'
        ];

        if (isset($methodMap[$mailList->name])) {
            $method = $methodMap[$mailList->name];
            $this->$method($mailList, $apiData);
        }
    }

    private function syncProspectSubscribers(MailList $mailList, array $apiData)
    {
        if (!isset($apiData['prospect_list']) || !is_array($apiData['prospect_list'])) {
            return;
        }

        foreach (array_chunk($apiData['prospect_list'], self::BATCH_SIZE) as $batch) {
            foreach ($batch as $data) {
                try {
                    if ($this->isValidEmail($data['email'] ?? null)) {
                        $this->upsertSubscriber($mailList, $data, 'prospect_id', $this->getProspectData($data));
                    }
                } catch (Exception $e) {
                    Log::error('Error syncing prospect subscriber: ' . $e->getMessage());
                }
            }
        }
    }

    private function syncNewsletterSubscribers(MailList $mailList, array $apiData)
    {
        if (!isset($apiData['news_letter_subscription'][0]) || !is_array($apiData['news_letter_subscription'][0])) {
            return;
        }

        foreach (array_chunk($apiData['news_letter_subscription'][0], self::BATCH_SIZE) as $batch) {
            foreach ($batch as $data) {
                try {
                    if ($this->isValidEmail($data['email'] ?? null)) {
                        $this->upsertSubscriber($mailList, $data, 'news_letter_subscription_id', $this->getNewsletterData($data));
                    }
                } catch (Exception $e) {
                    Log::error('Error syncing newsletter subscriber: ' . $e->getMessage());
                }
            }
        }
    }

    private function syncMemberSubscribers(MailList $mailList, array $apiData)
    {
        if (!isset($apiData['all_members_list'][0]) || !is_array($apiData['all_members_list'][0])) {
            return;
        }

        foreach (array_chunk($apiData['all_members_list'][0], self::BATCH_SIZE) as $batch) {
            foreach ($batch as $data) {
                try {
                    if ($this->isValidEmail($data['email'] ?? null)) {
                        $this->upsertSubscriber($mailList, $data, 'member_id', $this->getMemberData($data));
                    }
                } catch (Exception $e) {
                    Log::error('Error syncing member subscriber: ' . $e->getMessage());
                }
            }
        }
    }

    private function syncEventSubscribers(MailList $mailList, array $apiData)
    {
        if (!isset($apiData['event_list'][0]) || !is_array($apiData['event_list'][0])) {
            return;
        }

        foreach (array_chunk($apiData['event_list'][0], self::BATCH_SIZE) as $batch) {
            foreach ($batch as $data) {
                try {
                    if ($this->isValidEmail($data['email'] ?? null)) {
                        $this->upsertSubscriber($mailList, $data, 'event_id', $this->getEventData($data));
                    }
                } catch (Exception $e) {
                    Log::error('Error syncing event subscriber: ' . $e->getMessage());
                }
            }
        }
    }

    private function isValidEmail(?string $email): bool
    {
        return $email &&
            filter_var($email, FILTER_VALIDATE_EMAIL) &&
            !ctype_digit($email) &&
            strpos($email, '@') !== false;
    }

    private function upsertSubscriber(MailList $mailList, array $data, string $idField, array $subscriberData)
    {
        $subscriber = DB::table('subscribers')
            ->where($idField, $data['id'])
            ->where('mail_list_id', $mailList->id)
            ->first();

        $subscriberData = array_merge($subscriberData, [
            'mail_list_id' => $mailList->id,
            'ip' => Request::ip(),
            'status' => 'subscribed',
            'from' => 'added',
            'updated_at' => Carbon::now(),
        ]);

        if (!$subscriber) {
            $subscriberData = array_merge($subscriberData, [
                'uid' => (string) Str::uuid(),
                'created_at' => Carbon::now(),
            ]);
            DB::table('subscribers')->insert($subscriberData);
        } else {
            DB::table('subscribers')
                ->where($idField, $data['id'])
                ->where('mail_list_id', $mailList->id)
                ->update($subscriberData);
        }
    }

    private function getContact(array $data): int
    {
        $contactData = [
            'company' => $data['company'] ?? 'Stark and Barron Co',
            'phone' => $data['phone'] ?? '+923481234567',
            'email' => auth()->user()->email ?? 'sobodip@mailinator.com',
            'zip' => $data['zip'] ?? '58147',
            'state' => $data['state'] ?? 'Riverside Province',
            'city' => $data['city'] ?? 'Sialkot',
            'address_1' => $data['address_1'] ?? 'Address 1',
            'address_2' => $data['address_2'] ?? 'Address 2',
            'country_id' => $data['country_id'] ?? '154',
            'url' => $data['url'] ?? ''
        ];

        $contact = Contact::firstOrCreate(
            ['email' => $contactData['email']],
            $contactData
        );

        return $contact->id;
    }

    private function getProspectData(array $data): array
    {
        return [
            'email' => $data['email'],
            'user_email' => $data['email'],
            'name' => $data['full_name'],
            'prospect_id' => $data['id'],
            'propspect_Reference_number' => $data['reference_no'],
            'Company_name' => $data['company_name'],
            'phone' => $data['phone'],
            'jobrole' => $data['designation'],
            'prospect_vat_number' => $data['vat_number'],
            'prospect_address' => $data['address'],
            'xero_contact_id' => $data['xero_contact_id'],
        ];
    }

    private function getNewsletterData(array $data): array
    {
        return [
            'email' => $data['email'],
            'user_email' => $data['email'],
            'name' => trim($data['first_name'] . ' ' . $data['last_name']),
            'phone' => $data['mobile'],
            'news_letter_subscription_id' => $data['id'],
        ];
    }

    private function getMemberData(array $data): array
    {
        return [
            'email' => $data['email'],
            'user_email' => $data['email'],
            'name' => $data['name'],
            'member_id' => $data['id'],
            'member_title' => $data['title'] ?? '',
            'Profile_photo' => $data['image'],
            'Reference_number' => $data['reference_no'],
            'Company_name' => $data['company_name'],
            'phone' => $data['phone'],
            'gender' => $data['gender'],
            'bill_to_name' => $data['bill_to_name'],
            'bill_to_company' => $data['bill_to_company'],
            'bill_to_address' => $data['bill_to_address'],
            'subscription_start_date' => $data['subscription_start_date'],
            'subscription_end_date' => $data['subscription_end_date'],
            'jobrole' => $data['jobrole_title'],
            'activated' => $data['activated'] == '1' ? 'Active' : 'Inactive',
            'is_active' => $data['is_active'] == '1' ? 'Active' : 'Inactive',
            'created_by' => $data['created_by_name'],
            'date_of_birth' => $data['date_of_birth'],
            'vat_number' => $data['vat_number'],
            'sector__industries' => $data['sector__industries'],
            'account_type' => $data['account_type'],
            'membership_type' => $data['memb_type'] == 1 ? 'Individual' : 
              ($data['memb_type'] == 2 ? 'Business' : 
              ($data['memb_type'] == 3 ? 'Business Advance' : 'N/A')),
        ];
    }

    private function getEventData(array $data): array
    {
        return [
            'email' => $data['email'],
            'user_email' => $data['email'],
            'name' => $data['name'],
            'event_user_id' => $data['user_id'],
            'event_id' => $data['event_id'],
            'event_user_type' => $data['user_type'],
            'event_member_going' => $data['member_going'],
            'event_attended' => $data['attended'],
            'Reference_number' => $data['reference_no'],
            'Company_name' => $data['company_name'],
            'event_startDate' => $data['event_startDate'],
            'event_title' => $data['title'],
            'jobrole' => $data['job'],
            'event_status' => $data['status'],
            'event_primary_payer' => $data['primary_payer'],
            'sector__industries' => $data['sector__industries'],
            'p_reference_number' => $data['p_reference_number'],
            'payment_status' => $data['payment_status'],
            'fee' => $data['fee'],
            'tag_title' => $data['tag_title'] ?? '',
        ];
    }
}
