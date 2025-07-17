<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->after('author');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
        });
    }
};
