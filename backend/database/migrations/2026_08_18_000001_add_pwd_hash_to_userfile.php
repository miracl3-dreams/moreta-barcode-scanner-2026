<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('userfile') || Schema::hasColumn('userfile', 'pwd_hash')) {
            return;
        }

        Schema::table('userfile', function (Blueprint $table) {
            $table->string('pwd_hash', 255)->nullable()->after('usrpwd');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('userfile') || ! Schema::hasColumn('userfile', 'pwd_hash')) {
            return;
        }

        Schema::table('userfile', function (Blueprint $table) {
            $table->dropColumn('pwd_hash');
        });
    }
};
