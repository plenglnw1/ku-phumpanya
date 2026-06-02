<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

final class PhumpanyaMockCatalog
{
    /**
     * @return list<string>
     */
    public function suggestions(): array
    {
        return config('phumpanya_mock.suggestions', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $query): array
    {
        $slug = Str::slug(Str::lower(trim($query)));
        $topics = config('phumpanya_mock.topics', []);
        $topic = $topics[$slug] ?? $topics['default'];

        return array_merge($topic, [
            'slug' => $slug !== '' ? $slug : 'default',
            'title' => $topic['title'] ?? $query,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function smartPicks(): array
    {
        return config('phumpanya_mock.smart_picks', []);
    }
}
