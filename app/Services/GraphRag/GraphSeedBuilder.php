<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use Illuminate\Support\Str;

final class GraphSeedBuilder
{
    public function __construct(
        private readonly TripleExtractor $extractor,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $topics
     * @return array{docs: list<array<string, mixed>>, triples: list<array<string, mixed>>, links: list<array<string, mixed>>, paths: list<array<string, mixed>>}
     */
    public function build(array $topics): array
    {
        $docs = [];
        $triples = [];
        $paths = [];
        $linksMap = [];

        foreach ($topics as $topic) {
            $topicId = (string) ($topic['topic_id'] ?? Str::slug((string) ($topic['title'] ?? 'topic')));
            $title = (string) ($topic['title'] ?? 'Untitled Topic');
            $summary = (string) ($topic['summary'] ?? '');

            foreach (($topic['sources'] ?? []) as $index => $source) {
                $sourceName = (string) ($source['source'] ?? 'UNKNOWN');
                $docId = "{$topicId}-{$sourceName}-{$index}";

                $docs[] = [
                    'doc_id' => $docId,
                    'topic' => $topicId,
                    'title' => $title,
                    'content' => trim("{$summary}\nsource: {$sourceName}"),
                    'source' => $sourceName,
                    'section' => (string) ($source['section'] ?? 'seed'),
                    'url' => (string) ($source['url'] ?? ''),
                    'faculty_tags' => $topic['faculty_tags'] ?? [],
                    'bcg_tags' => $topic['bcg_tags'] ?? [],
                    'embedding' => $this->pseudoEmbedding("{$title} {$summary} {$sourceName}"),
                ];
            }

            foreach ($this->extractor->fromTopic($topic) as $triple) {
                $triples[] = $triple;
                $key = $this->linkKey((string) $triple['subject'], (string) $triple['object']);
                if (! isset($linksMap[$key])) {
                    $linksMap[$key] = [
                        'left_entity' => (string) $triple['subject'],
                        'right_entity' => (string) $triple['object'],
                        'relation' => (string) $triple['predicate'],
                        'weight' => 0,
                        'topic' => (string) $triple['topic'],
                    ];
                }
                $linksMap[$key]['weight']++;
            }

            $paths[] = [
                'path_id' => $topicId,
                'topic' => $topicId,
                'title' => $title,
                'description' => $summary,
                'courses' => $topic['courses'] ?? [],
                'faculty_tags' => $topic['faculty_tags'] ?? [],
                'bcg_tags' => $topic['bcg_tags'] ?? [],
                'estimated_hours' => $this->estimateHours($topic),
            ];
        }

        return [
            'docs' => $docs,
            'triples' => $triples,
            'links' => array_values($linksMap),
            'paths' => $paths,
        ];
    }

    private function linkKey(string $left, string $right): string
    {
        return Str::lower($left).'::'.Str::lower($right);
    }

    private function estimateHours(array $topic): string
    {
        $count = count($topic['courses'] ?? []);
        return match (true) {
            $count >= 3 => '120-180',
            $count === 2 => '90-140',
            default => '60-100',
        };
    }

    /**
     * @return list<float>
     */
    private function pseudoEmbedding(string $text): array
    {
        $hash = sha1($text);
        $vector = [];
        for ($i = 0; $i < 384; $i++) {
            $hex = substr($hash, ($i % 40), 2);
            $value = hexdec($hex);
            $vector[] = ($value / 255.0) * 2 - 1;
        }

        return $vector;
    }
}

