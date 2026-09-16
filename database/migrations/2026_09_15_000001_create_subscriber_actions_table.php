<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriberActionsTable extends Migration
{
    public function up()
    {
        // Agent-work tracking belongs solely to shaafi_portal.
        Schema::connection('mysql_portal')->create('subscriber_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id')->unique();
            $table->string('msisdn', 20);
            $table->enum('agent_status', ['PENDING', 'COMPLETED'])->default('PENDING');
            $table->unsignedBigInteger('done_by')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index('purchase_id');
            $table->index('msisdn');
            $table->index('agent_status');
            $table->foreign('done_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::connection('mysql_portal')->dropIfExists('subscriber_actions');
    }
}
