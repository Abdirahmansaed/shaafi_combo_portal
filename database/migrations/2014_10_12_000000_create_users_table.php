<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('mysql_portal')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstName')->nullable();
            $table->string('last_name')->nullable();
            $table->string('number', 20)->nullable();
            $table->string('password');
            $table->enum('status', ['ACTIVE', 'INIT', 'DELETED']);
            $table->string('username')->unique();
            // Required by Laravel's secure, cookie-based "remember me" flow.
            $table->rememberToken();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('mysql_portal')->dropIfExists('users');
    }
}
