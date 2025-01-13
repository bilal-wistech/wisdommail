<?php

namespace Database\Seeders;

use Acelle\Model\Field;
use Acelle\Model\MailList;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $mailListIds = MailList::pluck('id');

        // List of columns
        $columns = [
            'email' => 'EMAIL',
            'name' => 'NAME',
            'Profile_photo' => 'PROFILE_PHOTO',
            'Reference_number' => 'REFERENCE_NUMBER',
            'Company_name' => 'COMPANY_NAME',
            'phone' => 'PHONE',
            'gender' => 'GENDER',
            'date_of_birth' => 'DATE_OF_BIRTH',
            'vat_number' => 'VAT_NUMBER',
            'sector' => 'SECTOR',
            'bill_to_name' => 'BILL_TO_NAME',
            'bill_to_company' => 'BILL_TO_COMPANY',
            'bill_to_address' => 'BILL_TO_ADDRESS',
            'job_role' => 'JOB_ROLE',
            'linkedin' => 'LINKEDIN',
            'membership_type' => 'MEMBERSHIP_TYPE',
            'subscription_start_date' => 'SUBSCRIPTION_START_DATE',
            'subscription_end_date' => 'SUBSCRIPTION_END_DATE',
            'activated' => 'ACTIVATED',
            'is_active' => 'IS_ACTIVE',
            'created_by' => 'CREATED_BY',
            'jobrole' => 'JOBROLE',
            'sector__industries' => 'SECTOR_INDUSTRIES',
            'account_type' => 'ACCOUNT_TYPE',
            'event_id' => 'EVENT_ID',
            'event_title' => 'EVENT_TITLE',
            'event_startDate' => 'EVENT_START_DATE',
            'event_user_id' => 'EVENT_USER_ID',
            'event_user_type' => 'EVENT_USER_TYPE',
            'event_member_going' => 'EVENT_MEMBER_GOING',
            'event_attended' => 'EVENT_ATTENDED',
            'event_status' => 'EVENT_STATUS',
            'event_primary_payer' => 'EVENT_PRIMARY_PAYER',
            'event_reference_number' => 'EVENT_REFERENCE_NUMBER',
            'p_reference_number' => 'P_REFERENCE_NUMBER',
            'payment_status' => 'PAYMENT_STATUS',
            'fee' => 'FEE',
            'tag_title' => 'TAG_TITLE',
            'prospect_address' => 'PROSPECT_ADDRESS',
            'xero_contact_id' => 'XERO_CONTACT_ID',
            'prospect_vat_number' => 'PROSPECT_VAT_NUMBER',
            'propspect_Reference_number' => 'PROSPECT_REFERENCE_NUMBER',
            'member_title' => 'MEMBER_TITLE',
            'prospect_id' => 'PROSPECT_ID',
            'news_letter_subscription_id' => 'NEWS_LETTER_SUBSCRIPTION_ID',
        ];

        // Iterate through all MailList IDs
        foreach ($mailListIds as $mailListId) {
            $fieldsData = [];

            foreach ($columns as $label => $tag) {
                $fieldsData[] = [
                    'mail_list_id' => $mailListId,
                    'uid' => (string) Str::uuid(),
                    'label' => $label,
                    'type' => 'text',
                    'tag' => $tag,
                    'default_value' => null,
                    'visible' => 1,
                    'required' => 0,
                    'is_email' => $tag === 'EMAIL' ? 1 : 0,
                    'custom_field_name' => strtolower(str_replace(' ', '_', $label)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert fields data in chunks to optimize database interaction
            DB::table('fields')->insert($fieldsData);
        }

        $this->command->info('Fields table seeded successfully!');
    }
}
