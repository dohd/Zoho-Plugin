<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFollowUpMessages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('follow_up_messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('customer_rating_id');
            $table->string('message_sid');
            $table->text('message');
            $table->unsignedBigInteger('sent_by');
            $table->dateTime('sent_at');

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
        Schema::dropIfExists('follow_up_messages');
    }
}
