<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->integer('subscriber_id');
            $table->integer('member_id');
            $table->string('name');
            $table->string('email');
            $table->string('member_title')->nullable();
            $table->text('profile_photo')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('company_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->text('bill_to_name')->nullable();
            $table->text('bill_to_company')->nullable();
            $table->text('bill_to_address')->nullable();
            $table->date('subscription_start_date')->nullable();
            $table->date('subscription_end_date')->nullable();
            $table->string('job_role')->nullable();
            $table->enum('activated', ['Active', 'Inactive','N/A'])->default('N/A');
            $table->enum('is_active', ['Active', 'Inactive','N/A'])->default('N/A');
            $table->string('created_by')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('account_type')->nullable();
            $table->string('sector_industries')->nullable();
            $table->enum('membership_type', ['Individual', 'Business','Business Advance','N/A'])->default('N/A');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('members');
    }
}
