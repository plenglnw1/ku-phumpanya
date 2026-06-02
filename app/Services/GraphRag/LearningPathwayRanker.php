<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Str;

final class LearningPathwayRanker
{
    public function __construct(
        private readonly ElasticClientFactory $factory,
    ) {}

    /**
     * @param  list<string>  $queryTokens
     * @param  list<string>  $queryBcgTags
     * @param  list<string>  $queryFacultyTags
     * @return list<array<string, mixed>>
     */
    public function rank(array $queryTokens, array $queryBcgTags, array $queryFacultyTags, int $limit = 3): array
    {
        $paths = $this->fetchPaths();

        return collect($paths)
            ->map(function (array $path) use ($queryTokens, $queryBcgTags, $queryFacultyTags): array {
                $text = Str::lower(($path['title'] ?? '').' '.($path['description'] ?? ''));

                $tokenScore = collect($queryTokens)->reduce(
                    fn (int $carry, string $token): int => $carry + (Str::contains($text, $token) ? 2 : 0),
                    0,
                );

                $bcgScore = count(array_intersect($queryBcgTags, $path['bcg_tags'] ?? [])) * 3;
                $facultyScore = count(array_intersect($queryFacultyTags, $path['faculty_tags'] ?? [])) * 2;

                $path['score'] = $tokenScore + $bcgScore + $facultyScore;
                return $path;
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPaths(): array
    {
        $client = $this->factory->make();
        if (! $client instanceof Client) {
            return $this->fallbackPaths();
        }

        try {
            $response = $client->search([
                'index' => config('elasticsearch.indices.learning_paths'),
                'body' => [
                    'size' => 100,
                    'query' => ['match_all' => (object) []],
                ],
            ])->asArray();

            return array_map(
                static fn (array $hit): array => $hit['_source'] ?? [],
                $response['hits']['hits'] ?? [],
            );
        } catch (\Throwable) {
            return $this->fallbackPaths();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackPaths(): array
    {
        return collect(config('graphrag_seed.topics', []))
            ->map(fn (array $topic): array => [
                'path_id' => $topic['topic_id'],
                'topic' => $topic['topic_id'],
                'title' => $topic['title'],
                'description' => $topic['summary'],
                'courses' => $topic['courses'],
                'faculty_tags' => $topic['faculty_tags'],
                'bcg_tags' => $topic['bcg_tags'],
                'estimated_hours' => '90-140',
            ])
            ->values()
            ->all();
    }
}

