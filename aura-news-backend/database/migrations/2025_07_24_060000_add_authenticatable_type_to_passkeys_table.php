<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('passkeys', function (Blueprint $table) {
            $table->string('authenticatable_type')->default('App\\Models\\User')->after('authenticatable_id');
        });
    }

    public function down()
    {
        Schema::table('passkeys', function (Blueprint $table) {
            $table->dropColumn('authenticatable_type');
        });
    }
}; 