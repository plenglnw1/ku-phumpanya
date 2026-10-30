<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent;

use Illuminate\Support\Str;

/** Shared formatting for agent pipeline results. */
final class ResultFormatter
{
    public const SUBTITLE_AI = 'เส้นทางการเรียนรู้ที่ AI จัดลำดับจากแหล่งข้อมูลของ มก.';

    public const SUBTITLE_HEURISTIC = 'เส้นทางการเรียนรู้ที่จัดกลุ่มตามหัวข้อจากเอกสารที่ค้นพบ';

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return list<array<string, mixed>>
     */
    public static function toEvidence(array $documents): array
    {
        return collect($documents)
            ->map(function (array $doc): array {
                $ai = self::aiTranslation($doc);
                $evidence = [
                    'title' => (string) ($doc['title'] ?? 'Untitled'),
                    'source' => (string) ($doc['source'] ?? 'UNKNOWN'),
                    'url' => (string) ($doc['url'] ?? ''),
                    'snippet' => Str::limit(self::text($doc['content'] ?? $doc['abstract'] ?? ''), 150),
                ];
                if (isset($ai['title_ai_th'])) {
                    $evidence['title_ai_th'] = $ai['title_ai_th'];
                }
                if (isset($ai['abstract_ai_th'])) {
                    $evidence['snippet_ai_th'] = Str::limit($ai['abstract_ai_th'], 150);
                }
                if (isset($ai['ai_translation'])) {
                    $evidence['ai_translation'] = $ai['ai_translation'];
                }

                return $evidence;
            })
            ->values()
            ->all();
    }

    /**
     * AI translations stored beside an English original (`title_ai_th`, `abstract_ai_th`,
     * `ai_translation`). Only the keys a document actually has are returned, so results
     * for untranslated documents keep their old shape.
     *
     * @param  array<string, mixed>  $doc
     * @return array{title_ai_th?: string, abstract_ai_th?: string, ai_translation?: array<string, mixed>}
     */
    public static function aiTranslation(array $doc): array
    {
        $fields = [];
        foreach (['title_ai_th', 'abstract_ai_th'] as $key) {
            $value = self::text($doc[$key] ?? '');
            if ($value !== '') {
                $fields[$key] = $value;
            }
        }
        if (is_array($doc['ai_translation'] ?? null)) {
            $fields['ai_translation'] = $doc['ai_translation'];
        }

        return $fields;
    }

    /**
     * Keywords with their AI translations kept index-aligned. The translations are
     * dropped entirely when the stored lists differ in length, because a shifted list
     * would pair keywords with the wrong Thai term.
     *
     * @param  array<string, mixed>  $doc
     * @return array{keywords: list<string>, keywords_ai_th?: list<string>}
     */
    public static function keywords(array $doc, int $limit): array
    {
        $originals = array_values((array) ($doc['keywords'] ?? []));
        $translations = is_array($doc['keywords_ai_th'] ?? null) ? array_values($doc['keywords_ai_th']) : null;
        $aligned = $translations !== null && count($translations) === count($originals);

        $pairs = collect($originals)
            ->map(fn (mixed $k, int $i): array => [self::text($k), $aligned ? self::text($translations[$i]) : ''])
            ->filter(fn (array $pair): bool => $pair[0] !== '')
            ->take($limit)
            ->values();

        $result = ['keywords' => $pairs->pluck(0)->all()];
        if ($aligned && $pairs->isNotEmpty()) {
            $result['keywords_ai_th'] = $pairs->pluck(1)->all();
        }

        return $result;
    }

    /**
     * Copies document-level fields onto learning-path modules that point at a retrieved
     * document. LLM-written modules carry only title/url, so without this they would
     * lose the AI translations that heuristic modules get from the document itself.
     *
     * @param  array<string, mixed>  $learningPath
     * @param  list<array<string, mixed>>  $docs
     * @return array<string, mixed>
     */
    public static function withDocumentFields(array $learningPath, array $docs): array
    {
        $byUrl = collect($docs)->filter(fn (array $d): bool => ($d['url'] ?? '') !== '')->keyBy('url');
        if ($byUrl->isEmpty() || ! is_array($learningPath['phases'] ?? null)) {
            return $learningPath;
        }

        foreach ($learningPath['phases'] as $p => $phase) {
            foreach ((array) ($phase['modules'] ?? []) as $m => $module) {
                $doc = is_array($module) ? $byUrl->get((string) ($module['url'] ?? '')) : null;
                if ($doc !== null) {
                    $learningPath['phases'][$p]['modules'][$m] = $module + self::aiTranslation($doc) + LearningUnitOrder::moduleFields($doc);
                }
            }
        }

        return $learningPath;
    }

    /**
     * Corpus fields are strings in some sources and objects (e.g. `{name, affiliation}`)
     * or empty lists in others.
     */
    public static function text(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['name'] ?? $value['name_th'] ?? $value['th'] ?? $value['en'] ?? reset($value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * BCG pillars and pilot faculties of the retrieved documents, most frequent first.
     *
     * @param  list<array<string, mixed>>  $documents
     * @param  array<string, list<string>>  $topicFaculties  topic id => faculties, for
     *                                                       documents that carry `topic_ids` but no faculty field
     * @return array{bcg_pillars: list<string>, faculties: list<string>}
     */
    public static function tags(array $documents, array $topicFaculties = []): array
    {
        $documents = array_map(static function (array $doc) use ($topicFaculties): array {
            if (empty($doc['faculty_tags']) && empty($doc['faculties'])) {
                $faculties = [];
                foreach ((array) ($doc['topic_ids'] ?? []) as $id) {
                    array_push($faculties, ...($topicFaculties[(string) $id] ?? []));
                }
                $doc['faculties'] = $faculties;
            }

            return $doc;
        }, $documents);

        $count = static fn (string ...$keys): array => collect($documents)
            ->flatMap(static function (array $doc) use ($keys): array {
                foreach ($keys as $key) {
                    if (! empty($doc[$key]) && is_array($doc[$key])) {
                        return array_values(array_unique(array_map('strval', $doc[$key])));
                    }
                }

                return [];
            })
            ->filter(static fn (string $tag): bool => trim($tag) !== '')
            ->countBy()
            ->sortDesc()
            ->keys()
            ->map(static fn ($tag): string => (string) $tag)
            ->values()
            ->all();

        return [
            'bcg_pillars' => $count('bcg_tags', 'bcg_pillars'),
            'faculties' => $count('faculty_tags', 'faculties'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $learningPath
     * @param  list<array<string, mixed>>  $fallbackPhases  Already-built phases (e.g.
     *                                                      Synthesizer::groupIntoPhases on this query's own retrieved docs) — used
     *                                                      as-is, never re-ranked, so the fallback is guaranteed on-topic.
     * @return array<string, mixed>
     */
    public static function normalizeLearningPath(?array $learningPath, array $fallbackPhases = []): array
    {
        if ($learningPath !== null && ! empty($learningPath['phases'] ?? $learningPath['modules'] ?? null)) {
            return [
                'estimated_hours' => (string) ($learningPath['estimated_hours'] ?? ''),
                'subtitle' => (string) ($learningPath['subtitle'] ?? self::SUBTITLE_AI),
                'phases' => $learningPath['phases'] ?? $learningPath['modules'] ?? [],
            ];
        }

        return [
            'estimated_hours' => '',
            'subtitle' => self::SUBTITLE_HEURISTIC,
            'phases' => $fallbackPhases,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     */
    public static function docSummaryForPrompt(array $docs, int $limit = 5): string
    {
        return collect($docs)->take($limit)->map(function (array $doc, int $i): string {
            $title = $doc['title'] ?? 'Untitled';
            $source = $doc['source'] ?? '';
            $content = Str::limit((string) ($doc['content'] ?? $doc['abstract'] ?? ''), 200);

            return sprintf('[%d] %s (%s): %s', $i + 1, $title, $source, $content);
        })->implode("\n");
    }

    /**
     * @param  list<array<string, mixed>>  $relations
     */
    public static function relationsSummaryForPrompt(array $relations, int $limit = 10): string
    {
        return collect($relations)->take($limit)->map(fn (array $r): string => sprintf(
            '%s --[%s]--> %s',
            $r['subject'] ?? '?',
            $r['predicate'] ?? 'relatedTo',
            $r['object'] ?? '?',
        ))->implode("\n");
    }
}
