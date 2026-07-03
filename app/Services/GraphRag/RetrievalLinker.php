<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use Illuminate\Support\Str;

/**
 * Phase I — Retrieval Linking (EllieSQL schema linking analogue).
 * Produces context S': docs + relations + topics + entities.
 */
final class RetrievalLinker
{
    public function __construct(
        private readonly QdrantRetriever $qdrant,
        private readonly HybridRetriever $hybrid,
    ) {}

    /**
     * @return array{
     *     query: string,
     *     docs: list<array<string, mixed>>,
     *     relations: list<array<string, mixed>>,
     *     topics: list<string>,
     *     entities: list<string>
     * }
     */
    public function link(string $query): array
    {
        $topK = (int) config('qdrant.top_k', 6);
        $docs = $this->retrieveDocs($query, $topK);
        $relations = $this->retrieveRelations($query, 10);

        $topics = collect($docs)
            ->flatMap(fn (array $doc): array => $doc['topic_ids'] ?? [])
            ->unique()
            ->values()
            ->all();

        $entities = $this->extractEntities($query, $docs, $relations);

        return [
            'query' => $query,
            'docs' => $docs,
            'relations' => $relations,
            'topics' => $topics,
            'entities' => $entities,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function retrieveDocs(string $query, int $size): array
    {
        if (config('qdrant.enabled')) {
            $docs = $this->qdrant->retrieve($query, $size);
            if (! empty($docs)) {
                return $this->normalizeDocs($docs);
            }
        }

        return $this->normalizeDocs($this->hybrid->retrieve($query, $size));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function retrieveRelations(string $query, int $size): array
    {
        if (config('qdrant.enabled')) {
            $relations = $this->qdrant->retrieveRelations($query, $size);
            if (! empty($relations)) {
                return $relations;
            }
        }

        return $this->fetchRelationsFromConfig($query);
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     * @return list<array<string, mixed>>
     */
    private function normalizeDocs(array $docs): array
    {
        return collect($docs)->map(function (array $doc): array {
            $content = (string) ($doc['content'] ?? $doc['abstract'] ?? '');

            return array_merge($doc, [
                'content' => $content,
                'bcg_tags' => $doc['bcg_tags'] ?? $doc['bcg_pillars'] ?? [],
                'faculty_tags' => $doc['faculty_tags'] ?? $doc['faculties'] ?? [],
            ]);
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     * @param  list<array<string, mixed>>  $relations
     * @return list<string>
     */
    private function extractEntities(string $query, array $docs, array $relations): array
    {
        $fromDocs = collect($docs)->flatMap(function (array $doc): array {
            return array_filter([
                $doc['title'] ?? null,
                ...($doc['bcg_tags'] ?? $doc['bcg_pillars'] ?? []),
                ...array_slice($doc['keywords'] ?? [], 0, 5),
            ]);
        });

        $fromRelations = collect($relations)->flatMap(fn (array $rel): array => [
            $rel['subject'] ?? null,
            $rel['object'] ?? null,
        ]);

        return collect([$query])
            ->merge($fromDocs)
            ->merge($fromRelations)
            ->filter()
            ->map(fn (mixed $v): string => (string) $v)
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRelationsFromConfig(string $query): array
    {
        $terms = collect(explode(' ', Str::lower($query)))->filter();

        return collect(config('graphrag_seed.topics', []))
            ->flatMap(function (array $topic) use ($terms): array {
                $relations = [];
                foreach (($topic['faculty_tags'] ?? []) as $faculty) {
                    $relations[] = [
                        'subject' => $topic['title'] ?? 'Topic',
                        'predicate' => 'linkedFaculty',
                        'object' => $faculty,
                        'topic_id' => $topic['topic_id'] ?? '',
                    ];
                }
                foreach (($topic['courses'] ?? []) as $course) {
                    $relations[] = [
                        'subject' => $topic['title'] ?? 'Topic',
                        'predicate' => 'hasCourse',
                        'object' => $course['course_id'] ?? 'course',
                        'topic_id' => $topic['topic_id'] ?? '',
                    ];
                }

                return $relations;
            })
            ->filter(function (array $rel) use ($terms): bool {
                $text = Str::lower(($rel['subject'] ?? '').' '.($rel['object'] ?? ''));

                return $terms->contains(fn (string $term): bool => Str::contains($text, $term));
            })
            ->take(10)
            ->values()
            ->all();
    }
}
