<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class SearchHistorySeeder extends Seeder
{
    public function run(): void
    {
        $queries = [
            'Exploring Quantum Computing papers',
            'Summary of Climate Change 2024',
            'Key findings in Neural Networks',
            'Impact of AI in Medicine',
            'Analysis of Carbon Nanotubes',
        ];

        $users = User::query()->where('role', '!=', \App\Enums\UserRole::Admin)->get();

        foreach ($queries as $index => $query) {
            $user = $users->get($index % max($users->count(), 1));

            if ($user === null) {
                continue;
            }

            SearchHistory::query()->create([
                'user_id' => $user->id,
                'query' => $query,
                'created_at' => now()->subDays($index),
            ]);
        }
    }
}
