<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->integer('subscriber_id');
            $table->integer('event_id');
            $table->text('event_title');
            $table->string('name');
            $table->string('email');
            $table->string('event_user_type')->nullable();
            $table->enum('event_member_going', ['Yes', 'No','N/A'])->default('N/A');
            $table->enum('event_attended', ['Yes', 'No','N/A'])->default('N/A');
            $table->string('reference_number')->nullable();
            $table->text('company_name')->nullable();
            $table->date('event_start_date')->nullable();
            $table->date('event_end_date')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('job_role')->nullable();
            $table->enum('status', ['Approved', 'Pending','Cancelled','Completed','N/A'])->default('N/A');
            $table->string('event_primary_player')->nullable();
            $table->string('sector_industries')->nullable();
            $table->enum('membership_type', ['Individual', 'Business','Business Advance','N/A'])->default('N/A');
            $table->enum('payment_status', ['Paid', 'Unpaid','Partial Paid','N/A'])->default('N/A');
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
        Schema::dropIfExists('events');
    }
}
