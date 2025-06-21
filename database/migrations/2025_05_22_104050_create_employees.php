<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployees extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('payroll_no')->nullable();
            $table->bigInteger('id_no')->nullable();
            $table->string('tax_pin', 50)->nullable();
            $table->string('salutation', 50)->nullable();
            $table->string('surname', 50)->nullable();
            $table->string('first_name', 50)->nullable();
            $table->string('other_name', 50)->nullable();
            $table->bigInteger('birth_date')->nullable();
            $table->string('gender', 50)->nullable();
            $table->string('blood_group', 50)->nullable();
            $table->string('marital', 50)->nullable();
            $table->bigInteger('children')->nullable();
            $table->bigInteger('ethnicity')->nullable();
            $table->bigInteger('religion')->nullable();
            $table->bigInteger('education_peak')->nullable();
            $table->bigInteger('home_county')->nullable();
            $table->bigInteger('home_subcounty')->nullable();
            $table->bigInteger('home_ward')->nullable();
            $table->bigInteger('home_village')->nullable();
            $table->bigInteger('postal_box_no')->nullable();
            $table->bigInteger('postal_code')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 50)->nullable();
            $table->bigInteger('special_needs')->nullable();
            $table->string('paygroup', 50)->nullable();
            $table->string('job_desig', 50)->nullable();
            $table->string('deploy_title', 50)->nullable();
            $table->string('job_group', 50)->nullable();
            $table->bigInteger('date_hired')->nullable();
            $table->bigInteger('date_of_post')->nullable();
            $table->string('engagement_type', 50)->nullable();
            $table->bigInteger('contract_end_date')->nullable();
            $table->bigInteger('work_county')->nullable();
            $table->bigInteger('work_subcounty')->nullable();
            $table->bigInteger('work_ward')->nullable();
            $table->bigInteger('work_village')->nullable();
            $table->bigInteger('duty_station')->nullable();
            $table->bigInteger('bank')->nullable();
            $table->bigInteger('branch')->nullable();
            $table->bigInteger('bank_account')->nullable();
            $table->bigInteger('detachment')->nullable();
            $table->bigInteger('detachment_date')->nullable();
            $table->decimal('gross_salary', 16, 4)->default(0);
            $table->string('desig_name', 50)->nullable();
            $table->bigInteger('station_code')->nullable();
            $table->string('station_name', 50)->nullable();
            $table->bigInteger('building_no')->nullable();
            $table->string('building_name', 50)->nullable();
            $table->string('street', 50)->nullable();
            $table->bigInteger('floor_no')->nullable();
            $table->string('who_contact', 50)->nullable();
            $table->bigInteger('gps_x')->nullable();
            $table->bigInteger('gps_y')->nullable();
            $table->bigInteger('x_station')->nullable();
            $table->bigInteger('ethnicity_code')->nullable();
            $table->string('ethnicity_name', 50)->nullable();
            $table->bigInteger('skill_level_code')->nullable();
            $table->string('skill_level_name', 50)->nullable();

            $table->bigInteger('user_id')->nullable();
            $table->bigInteger('ins')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employees');
    }
}
