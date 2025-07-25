<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('passkeys', function (Blueprint $table) {
            $table->string('email')->nullable()->after('authenticatable_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passkeys', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
