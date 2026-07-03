<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_histories', function (Blueprint $table) {
            $table->json('result')->nullable()->after('query');
        });
    }

    public function down(): void
    {
        Schema::table('search_histories', function (Blueprint $table) {
            $table->dropColumn('result');
        });
    }
};
