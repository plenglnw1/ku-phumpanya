<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class SearchHistorySeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            [
                'query' => 'AI Agent ในงานเกษตร',
                'status' => 'success',
                'query_type' => 'complex',
                'latency' => 450,
                'nodes' => 5,
                'labels' => ['ดร.สมชาย', 'AI Agriculture', 'Carbon Credit'],
                'days_ago' => 1,
            ],
            [
                'query' => 'KU MOOC Python basic',
                'status' => 'success',
                'query_type' => 'simple',
                'latency' => 210,
                'nodes' => 3,
                'labels' => ['Python Basics', 'KU MOOC'],
                'days_ago' => 2,
            ],
            [
                'query' => 'Quantum biology plants',
                'status' => 'zero_results',
                'query_type' => 'simple',
                'latency' => 180,
                'nodes' => 0,
                'labels' => [],
                'days_ago' => 3,
            ],
            [
                'query' => 'Carbon Credit',
                'status' => 'success',
                'query_type' => 'simple',
                'latency' => 320,
                'nodes' => 4,
                'labels' => ['Carbon Credit', 'BCG Economy'],
                'days_ago' => 4,
            ],
            [
                'query' => 'Smart Farming',
                'status' => 'success',
                'query_type' => 'complex',
                'latency' => 510,
                'nodes' => 6,
                'labels' => ['Smart Farming', 'IoT Sensors', 'Course: Data Sci'],
                'days_ago' => 5,
            ],
            [
                'query' => 'AI Agent ในงานเกษตร',
                'status' => 'success',
                'query_type' => 'complex',
                'latency' => 390,
                'nodes' => 4,
                'labels' => ['ดร.สมชาย', 'Smart Farming'],
                'days_ago' => 6,
            ],
            [
                'query' => 'Impact of AI in Medicine',
                'status' => 'success',
                'query_type' => 'simple',
                'latency' => 280,
                'nodes' => 3,
                'labels' => ['AI Medicine', 'Health Tech'],
                'days_ago' => 0,
            ],
            [
                'query' => 'Climate Change 2024',
                'status' => 'error',
                'query_type' => 'simple',
                'latency' => 1200,
                'nodes' => 0,
                'labels' => [],
                'days_ago' => 1,
            ],
        ];

        $users = User::query()->where('role', '!=', UserRole::Admin)->get();

        foreach ($samples as $index => $sample) {
            $user = $users->get($index % max($users->count(), 1));

            if ($user === null) {
                continue;
            }

            SearchHistory::query()->create([
                'user_id' => $user->id,
                'query' => $sample['query'],
                'result' => null,
                'status' => $sample['status'],
                'query_type' => $sample['query_type'],
                'role_snapshot' => $user->role?->value,
                'faculty_snapshot' => $user->faculty,
                'total_latency_ms' => $sample['latency'],
                'retrieval_latency_ms' => (int) round($sample['latency'] * 0.35),
                'synthesis_latency_ms' => (int) round($sample['latency'] * 0.55),
                'total_nodes_found' => $sample['nodes'],
                'metrics' => [
                    'decomposed_queries' => $sample['query_type'] === 'complex'
                        ? ['งานวิจัย AI Agent', 'รายชื่อนักวิจัย']
                        : [],
                    'nodes_breakdown' => [
                        'topic' => min(2, $sample['nodes']),
                        'faculty' => min(1, $sample['nodes']),
                        'course' => max(0, $sample['nodes'] - 3),
                    ],
                    'top_node_labels' => $sample['labels'],
                    'docs_retrieved' => $sample['nodes'] > 0 ? $sample['nodes'] + 2 : 0,
                    'relations_retrieved' => $sample['nodes'] > 0 ? $sample['nodes'] : 0,
                    'error_message' => $sample['status'] === 'error' ? 'Upstream timeout' : null,
                ],
                'created_at' => now()->subDays($sample['days_ago'])->subHours($index),
                'updated_at' => now()->subDays($sample['days_ago'])->subHours($index),
            ]);
        }
    }
}
