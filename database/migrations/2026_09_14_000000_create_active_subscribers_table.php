<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActiveSubscribersTable extends Migration
{
    public function up()
    {
        Schema::connection('mysql_portal')->create('active_subscribers', function (Blueprint $table) {
            $table->id();
            // This source ID prevents a purchase from receiving duplicate work records.
            $table->unsignedBigInteger('business_purchase_id')->unique();
            $table->string('subscriber_number', 20);
            $table->enum('package_tier', ['DAILY', 'WEEKLY', 'MONTHLY']);
            $table->dateTime('purchase_date');
            $table->dateTime('expire_date');
            $table->enum('action_status', ['PENDING', 'COMPLETED'])->default('PENDING');
            $table->unsignedBigInteger('done_by')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('done_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::connection('mysql_portal')->dropIfExists('active_subscribers');
    }
}
