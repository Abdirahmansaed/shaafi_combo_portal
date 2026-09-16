<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleToPortalUsersTable extends Migration
{
    public function up()
    {
        // Explicitly uses shaafi_portal's mysql_portal connection, never mydatabase.
        Schema::connection('mysql_portal')->table('users', function (Blueprint $table) {
            $table->enum('role', ['SUPERADMIN', 'AGENT'])->default('AGENT')->after('status');
        });
    }

    public function down()
    {
        Schema::connection('mysql_portal')->table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
}
