<?php

namespace Acelle\Jobs;

use Exception;
use Carbon\Carbon;
use Acelle\Model\Field;
use Acelle\Model\MailList;
use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncMailListJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info('Starting mail lists synchronization...');

            $apiResponse = Http::get('https://britishchamberdubai.com/api/list-names');
            $apiData = json_decode($apiResponse->body(), true);
            //dd($apiData['Events List']);
            Log::info('API Data Retrieved', ['api_data' => $apiData]);
            $upsertData = [];

            // Check if the MailList table is empty
            if (MailList::count() == 0) {
                foreach ($apiData['lists'] as $data) {
                    // Prepare data for MailList insertion
                    $mailListData = [
                        'name' => $data['title'],
                        'uid' => (string) \Illuminate\Support\Str::uuid(),
                        'customer_id' => 1,
                        'contact_id' => $this->get_contact($data),
                        'subscribe_confirmation' => 0,
                        'unsubscribe_notification' => 0,
                        'send_welcome_email' => 0,
                        'from_email' => env('MAIL_FROM_ADDRESS'),
                        'from_name' => 'BILAL',
                    ];

                    // Insert MailList and get the inserted ID
                    $mailListId = MailList::insertGetId($mailListData);

                    // Prepare field data
                    $fieldsData = [
                        [
                            'mail_list_id' => $mailListId,
                            'uid' => (string) \Illuminate\Support\Str::uuid(),
                            'label' => 'Email',
                            'type' => 'text',
                            'tag' => 'EMAIL',
                            'default_value' => NULL,
                            'visible' => 1,
                            'required' => 1,
                            'is_email' => 1,
                            'custom_field_name' => "custom_100",
                        ],
                        [
                            'mail_list_id' => $mailListId,
                            'uid' => (string) \Illuminate\Support\Str::uuid(),
                            'label' => 'First name',
                            'type' => 'text',
                            'tag' => 'FIRST_NAME',
                            'default_value' => NULL,
                            'visible' => 1,
                            'required' => 0,
                            'is_email' => 0,
                            'custom_field_name' => "custom_101",
                        ],
                        [
                            'mail_list_id' => $mailListId,
                            'uid' => (string) \Illuminate\Support\Str::uuid(),
                            'label' => 'Last name',
                            'type' => 'text',
                            'tag' => 'LAST_NAME',
                            'default_value' => NULL,
                            'visible' => 1,
                            'required' => 0,
                            'is_email' => 0,
                            'custom_field_name' => "custom_102",
                        ],
                    ];

                    // Insert fields data
                    Field::insert($fieldsData);
                }
            } else {

                foreach ($apiData['lists'] as $data) {
                    $existingRecord = MailList::where('name', $data['title'])->first();

                    if ($existingRecord) {
                        $existingRecord->update([
                            'name' => $data['title']
                        ]);
                    } else {
                        $mailListId = MailList::insertGetId([
                            'name' => $data['title'],
                            'uid' => (string) \Illuminate\Support\Str::uuid(),
                            'customer_id' => 1,
                            'contact_id' => $this->get_contact($data),
                            'subscribe_confirmation' => 0,
                            'unsubscribe_notification' => 0,
                            'send_welcome_email' => 0,
                            'from_email' => env('MAIL_FROM_ADDRESS'),
                            'from_name' => 'BILAL',
                        ]);

                        $fieldsData = [
                            [
                                'mail_list_id' => $mailListId,
                                'uid' => (string) \Illuminate\Support\Str::uuid(),
                                'label' => 'Email',
                                'type' => 'text',
                                'tag' => 'EMAIL',
                                'default_value' => NULL,
                                'visible' => 1,
                                'required' => 1,
                                'is_email' => 1,
                                'custom_field_name' => "custom_100",
                            ],
                            [
                                'mail_list_id' => $mailListId,
                                'uid' => (string) \Illuminate\Support\Str::uuid(),
                                'label' => 'First name',
                                'type' => 'text',
                                'tag' => 'FIRST_NAME',
                                'default_value' => NULL,
                                'visible' => 1,
                                'required' => 0,
                                'is_email' => 0,
                                'custom_field_name' => "custom_101",
                            ],
                            [
                                'mail_list_id' => $mailListId,
                                'uid' => (string) \Illuminate\Support\Str::uuid(),
                                'label' => 'Last name',
                                'type' => 'text',
                                'tag' => 'LAST_NAME',
                                'default_value' => NULL,
                                'visible' => 1,
                                'required' => 0,
                                'is_email' => 0,
                                'custom_field_name' => "custom_102",
                            ],
                        ];

                        // Insert fields data
                        Field::insert($fieldsData);
                    }
                }
            }

            $selected_mail_List = DB::table('mail_lists')->get();
            // DB::table('subscribers')->delete();
            foreach ($selected_mail_List as $mail_list) {
                switch ($mail_list->name) {
                    case 'Prospect List':
                        // Check if 'prospect_list' exists and is an array
                        if (isset($apiData['prospect_list']) && is_array($apiData['prospect_list'])) {
                            foreach ($apiData['prospect_list'] as $emailData) {
                                // Ensure $emailData is an array and contains an 'email' key
                                if (isset($emailData['email'])) {
                                    $email = $emailData['email'];

                                    // Validate email: ensure it's not numeric-only or name-only
                                    if (
                                        filter_var($email, FILTER_VALIDATE_EMAIL) &&
                                        !ctype_digit($email) && // Not only numbers
                                        preg_match('/@/', $email) // Contains "@" for email structure
                                    ) {
                                        // Check if the email already exists for this mail_list_id
                                        $existingEmail = DB::table('subscribers')
                                            ->where('email', $email)
                                            ->where('mail_list_id', $mail_list->id)
                                            ->first();

                                        if (!$existingEmail) {
                                            // Insert new email if it doesn't exist
                                            DB::table('subscribers')->insert([
                                                'uid' => (string) \Illuminate\Support\Str::uuid(),
                                                'email' => $email,
                                                'mail_list_id' => $mail_list->id,
                                                'ip' => Request::ip(),
                                                'status' => 'subscribed',
                                                'from' => 'added',
                                                'custom_100' => $email,
                                                'custom_101' => $emailData['full_name'],
                                                'custom_102' => '--',
                                                'created_at' => Carbon::now(),
                                                'updated_at' => Carbon::now(),
                                                'propspect_Reference_number' => $emailData['reference_no'],
                                                'Company_name' => $emailData['company_name'],
                                                'phone' => $emailData['phone'],
                                                'jobrole' => $emailData['designation'],
                                                'prospect_vat_number' => $emailData['vat_number'],
                                                'prospect_address' => $emailData['address'],
                                                'xero_contact_id' => $emailData['xero_contact_id'],
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                        break;

                    case 'News Letter Subscription':
                        // Check if 'news_letter_subscription' exists and is an array
                        if (isset($apiData['news_letter_subscription'][0]) && is_array($apiData['news_letter_subscription'][0])) {
                            foreach ($apiData['news_letter_subscription'][0] as $emailData) {
                                // Ensure $emailData is an array and contains an 'email' key
                                if (isset($emailData['email'])) {
                                    $email = $emailData['email'];

                                    // Validate email: ensure it's not numeric-only or name-only
                                    if (
                                        filter_var($email, FILTER_VALIDATE_EMAIL) &&
                                        !ctype_digit($email) && // Not only numbers
                                        preg_match('/@/', $email) // Contains "@" for email structure
                                    ) {
                                        // Check if the email already exists for this mail_list_id
                                        $existingEmail = DB::table('subscribers')
                                            ->where('email', $email)
                                            ->where('mail_list_id', $mail_list->id)
                                            ->first();

                                        if (!$existingEmail) {
                                            // Insert new email if it doesn't exist
                                            DB::table('subscribers')->insert([
                                                'uid' => (string) \Illuminate\Support\Str::uuid(),
                                                'email' => $email,
                                                'mail_list_id' => $mail_list->id,
                                                'ip' => Request::ip(),
                                                'status' => 'subscribed',
                                                'from' => 'added',
                                                'custom_100' => $email,
                                                'custom_101' => $emailData['first_name'],
                                                'custom_102' => $emailData['last_name'],
                                                'phone' => $emailData['mobile'],
                                                'created_at' => Carbon::now(),
                                                'updated_at' => Carbon::now(),
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                        break;

                    case 'All Members':
                        // Check if 'all_members_list' exists and is an array
                        if (isset($apiData['all_members_list'][0]) && is_array($apiData['all_members_list'][0])) {
                            foreach ($apiData['all_members_list'][0] as $emailData) {
                                // Ensure $emailData is an array and contains an 'email' key
                                if (isset($emailData['email'])) {
                                    $email = $emailData['email'];

                                    // Validate email: ensure it's not numeric-only or name-only
                                    if (
                                        filter_var($email, FILTER_VALIDATE_EMAIL) &&
                                        !ctype_digit($email) && // Not only numbers
                                        preg_match('/@/', $email) // Contains "@" for email structure
                                    ) {
                                        // Check if the email already exists for this mail_list_id
                                        $existingEmail = DB::table('subscribers')
                                            ->where('email', $email)
                                            ->where('mail_list_id', $mail_list->id)
                                            ->first();

                                        if (!$existingEmail) {
                                            // Insert new email if it doesn't exist
                                            DB::table('subscribers')->insert([
                                                'uid' => (string) \Illuminate\Support\Str::uuid(),
                                                'email' => $email,
                                                'mail_list_id' => $mail_list->id,
                                                'ip' => Request::ip(),
                                                'status' => 'subscribed',
                                                'from' => 'added',
                                                'custom_100' => $email,
                                                'custom_101' => $emailData['title'],
                                                'custom_102' => $emailData['name'],
                                                'created_at' => Carbon::now(),
                                                'updated_at' => Carbon::now(),
                                                'user_id' => $emailData['id'],
                                                'Profile_photo' => $emailData['image'],
                                                'Reference_number' => $emailData['reference_no'],
                                                'Company_name' => $emailData['company_name'],
                                                'phone' => $emailData['phone'],
                                                'gender' => $emailData['gender'],
                                                'bill_to_name' => $emailData['bill_to_name'],
                                                'bill_to_company' => $emailData['bill_to_company'],
                                                'bill_to_address' => $emailData['bill_to_address'],
                                                'subscription_start_date' => $emailData['subscription_start_date'],
                                                'subscription_end_date' => $emailData['subscription_end_date'],
                                                'jobrole' => $emailData['jobrole_title'],
                                                'previous_status' => $emailData['activated'],
                                                'actual_active' => $emailData['is_active'],
                                                'created_by' => $emailData['created_by_name'],
                                                'date_of_birth' => $emailData['date_of_birth'],
                                                'vat_number' => $emailData['vat_number'],
                                                'sector__industries' => $emailData['sector__industries'],
                                                'account_type' => $emailData['account_type'],
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                        break;

                    case 'Events List':
                        // Check if 'event_list' exists and is an array
                        if (isset($apiData['event_list'][0]) && is_array($apiData['event_list'][0])) {
                            foreach ($apiData['event_list'][0] as $emailData) {
                                // Ensure $emailData is an array and contains an 'email' key
                                if (isset($emailData['email'])) {
                                    $email = $emailData['email'];

                                    // Validate email: ensure it's not numeric-only or name-only
                                    if (
                                        filter_var($email, FILTER_VALIDATE_EMAIL) &&
                                        !ctype_digit($email) && // Not only numbers
                                        preg_match('/@/', $email) // Contains "@" for email structure
                                    ) {
                                        // Check if the email already exists for this mail_list_id
                                        $existingEmail = DB::table('subscribers')
                                            ->where('email', $email)
                                            ->where('mail_list_id', $mail_list->id)
                                            ->first();

                                        if (!$existingEmail) {
                                            // Insert new email if it doesn't exist
                                            DB::table('subscribers')->insert([
                                                'uid' => (string) \Illuminate\Support\Str::uuid(),
                                                'email' => $email,
                                                'mail_list_id' => $mail_list->id,
                                                'ip' => Request::ip(),
                                                'status' => 'subscribed',
                                                'from' => 'added',
                                                'custom_100' => $email,
                                                'custom_101' => $emailData['name'],
                                                'custom_102' => '--',
                                                'created_at' => Carbon::now(),
                                                'updated_at' => Carbon::now(),
                                                'user_id' => $emailData['user_id'],
                                                'event_user_type' => $emailData['user_type'],
                                                'event_member_going' => $emailData['member_going'],
                                                'event_attended' => $emailData['attended'],
                                                'Reference_number' => $emailData['reference_no'],
                                                'Company_name' => $emailData['company_name'],
                                                'event_startDate' => $emailData['event_startDate'],
                                                'event_title' => $emailData['title'],
                                                'jobrole' => $emailData['job'],
                                                'event_status' => $emailData['status'],
                                                'event_primary_payer' => $emailData['primary_payer'],
                                                'sector__industries' => $emailData['sector__industries'],
                                                'p_reference_number' => $emailData['p_reference_number'],
                                                'payment_status' => $emailData['payment_status'],
                                                'fee' => $emailData['fee'],
                                                'tag_title' => $emailData['tag_title'],
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                        break;
                }
            }
            Log::info('Mail lists synchronization completed successfully.');
        } catch (Exception $e) {
            Log::error('Mail lists synchronization failed: ' . $e->getMessage());

            // Determine if we should retry
            if ($this->attempts() >= $this->tries) {
                Log::error('Max retry attempts reached. Failing job permanently.');
                $this->fail($e);
            } else {
                throw $e; // This will trigger a retry based on the backoff setting
            }
        }
    }
    private function get_contact($data)
    {
        $contactData = [
            "company" => $data['company'] ?? "Stark and Barron Co",
            "phone" => $data['phone'] ?? "+923481234567",
            "email" => auth()->user()->email ?? "sobodip@mailinator.com",
            "zip" => $data['zip'] ?? "58147",
            "state" => $data['state'] ?? "Riverside Province",
            "city" => $data['city'] ?? "Sialkot",
            "address_1" => $data['address_1'] ?? "Address 1",
            "address_2" => $data['address_2'] ?? "Address 2",
            "country_id" => $data['country_id'] ?? "154",
            "url" => $data['url'] ?? ""
        ];

        // Check if the contact exists by email
        $contact = \Acelle\Model\Contact::where('email', $contactData['email'])->first();

        if ($contact) {
            // Update existing contact
            $contact->update($contactData);
        } else {
            // Create a new contact
            $contact = \Acelle\Model\Contact::create($contactData);
        }

        // Return the contact's ID
        return $contact->id;
    }
    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('SyncMailListsJob failed permanently: ' . $exception->getMessage());

        // Add any cleanup or notification logic here
        // For example, you could send an email to admin or update a status in database
    }
}
