<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_histories', function (Blueprint $table): void {
            $table->string('status')->default('success')->after('result');
            $table->string('query_type')->nullable()->after('status');
            $table->string('role_snapshot')->nullable()->after('query_type');
            $table->string('faculty_snapshot')->nullable()->after('role_snapshot');
            $table->unsignedInteger('total_latency_ms')->nullable()->after('faculty_snapshot');
            $table->unsignedInteger('retrieval_latency_ms')->nullable()->after('total_latency_ms');
            $table->unsignedInteger('synthesis_latency_ms')->nullable()->after('retrieval_latency_ms');
            $table->unsignedInteger('total_nodes_found')->nullable()->after('synthesis_latency_ms');
            $table->json('metrics')->nullable()->after('total_nodes_found');

            $table->index(['created_at', 'status']);
            $table->index('role_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('search_histories', function (Blueprint $table): void {
            $table->dropIndex(['created_at', 'status']);
            $table->dropIndex(['role_snapshot']);
            $table->dropColumn([
                'status',
                'query_type',
                'role_snapshot',
                'faculty_snapshot',
                'total_latency_ms',
                'retrieval_latency_ms',
                'synthesis_latency_ms',
                'total_nodes_found',
                'metrics',
            ]);
        });
    }
};
