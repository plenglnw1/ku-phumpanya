<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent;

use Illuminate\Support\Str;

/**
 * Phase II — Route query to Basic / Intermediate / Advanced tier.
 */
final class QueryRouter
{
    public function __construct(
        private readonly GeminiClient $gemini,
    ) {}

    /**
     * @param  array{query: string, docs: list<array>, relations: list<array>, topics: list<string>, entities: list<string>}  $context
     * @return array{tier: string, reason: string}
     */
    public function route(array $context): array
    {
        $forced = (string) config('gemini.force_tier', '');
        if (in_array($forced, ['basic', 'intermediate', 'advanced'], true)) {
            return ['tier' => $forced, 'reason' => 'forced via GEMINI_FORCE_TIER'];
        }

        if (config('gemini.enabled') && config('gemini.api_key') !== '') {
            $ai = $this->routeWithGemini($context);
            if ($ai !== null) {
                return $ai;
            }
        }

        return $this->heuristicRoute($context);
    }

    /**
     * @param  array{query: string, docs: list<array>, relations: list<array>, topics: list<string>}  $context
     * @return array{tier: string, reason: string}|null
     */
    private function routeWithGemini(array $context): ?array
    {
        $prompt = sprintf(
            "Classify this KU BCG research query complexity.\n\nQuery: %s\nDocs retrieved: %d\nRelations: %d\nTopics: %d\n\nReturn JSON with tier (basic|intermediate|advanced) and reason.\n- basic: simple lookup, single concept\n- intermediate: multi-source synthesis, 2 topics\n- advanced: comparison, cross-faculty chains, learning path design",
            $context['query'],
            count($context['docs']),
            count($context['relations']),
            count($context['topics']),
        );

        $schema = [
            'type' => 'object',
            'properties' => [
                'tier' => ['type' => 'string', 'enum' => ['basic', 'intermediate', 'advanced']],
                'reason' => ['type' => 'string'],
            ],
            'required' => ['tier', 'reason'],
        ];

        $result = $this->gemini->generateJson(
            (string) config('gemini.models.router'),
            $prompt,
            $schema,
        );

        if ($result === null || ! in_array($result['tier'] ?? '', ['basic', 'intermediate', 'advanced'], true)) {
            return null;
        }

        return [
            'tier' => (string) $result['tier'],
            'reason' => (string) ($result['reason'] ?? 'gemini router'),
        ];
    }

    /**
     * @param  array{query: string, docs: list<array>, relations: list<array>, topics: list<string>}  $context
     * @return array{tier: string, reason: string}
     */
    private function heuristicRoute(array $context): array
    {
        $query = Str::lower($context['query']);
        $wordCount = str_word_count($query);
        $topicCount = count($context['topics']);
        $relationCount = count($context['relations']);

        $advancedKeywords = ['compare', 'relationship', 'path', 'learning', 'cross', 'เปรียบเทียบ', 'เชื่อมโยง', 'เส้นทาง'];
        $intermediateKeywords = ['how', 'why', 'impact', 'effect', 'อย่างไร', 'ส่งผล', 'มีผล'];

        foreach ($advancedKeywords as $kw) {
            if (Str::contains($query, $kw)) {
                return ['tier' => 'advanced', 'reason' => "keyword: {$kw}"];
            }
        }

        if ($topicCount >= 3 || $relationCount >= 5 || $wordCount >= 12) {
            return ['tier' => 'advanced', 'reason' => 'multi-topic or long query'];
        }

        foreach ($intermediateKeywords as $kw) {
            if (Str::contains($query, $kw)) {
                return ['tier' => 'intermediate', 'reason' => "keyword: {$kw}"];
            }
        }

        if ($topicCount >= 2 || $relationCount >= 2) {
            return ['tier' => 'intermediate', 'reason' => 'multiple topics/relations'];
        }

        return ['tier' => 'basic', 'reason' => 'simple lookup'];
    }
}
