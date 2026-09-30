<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SearchHistory;
use App\Services\KnowledgeGraph\CypherGuard;
use App\Services\KnowledgeGraph\KnowledgeGraphException;
use App\Services\KnowledgeGraph\Neo4jClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Read-only explorer for the BCG knowledge graph (checklist 1.2) for the committee.
 */
final class KnowledgeGraphController extends Controller
{
    // Schema of KU-BCG elastic-search/neo4j_import.py. Keyword is left out of the
    // overview: document keywords make it the largest label by far.
    private const DOMAIN_LABELS = ['Topic', 'Faculty', 'BcgPillar', 'Course', 'Entity'];

    private const HUB_ENTITY = 'CarbonFootprint';

    private const NEIGHBOUR_LIMIT = 60;

    public function __construct(
        private readonly Neo4jClient $neo4j,
        private readonly CypherGuard $guard,
    ) {}

    public function index(Request $request): View
    {
        return view('knowledge-graph.index', [
            'configured' => $this->neo4j->isConfigured(),
            'reports' => $this->reports(),
            'recentSearches' => SearchHistory::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function overview(): JsonResponse
    {
        return $this->respond(
            'MATCH (a)-[r]->(b) '
            .'WHERE any(l IN labels(a) WHERE l IN $labels) AND any(l IN labels(b) WHERE l IN $labels) '
            .'RETURN a, r, b',
            ['labels' => self::DOMAIN_LABELS],
        );
    }

    public function hub(): JsonResponse
    {
        return $this->respond(
            'MATCH (n:Entity {name: $name}) OPTIONAL MATCH (n)-[r]-(m) '
            .'WITH n, r, m LIMIT $limit RETURN n, r, m',
            ['name' => self::HUB_ENTITY, 'limit' => self::NEIGHBOUR_LIMIT],
        );
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return $this->respond(
            'MATCH (n) WITH n, coalesce(n.name, n.title, n.name_en, "") AS label, coalesce(n.name_th, "") AS thai '
            .'WHERE toLower(label) CONTAINS toLower($q) OR toLower(thai) CONTAINS toLower($q) '
            .'RETURN n ORDER BY size(label) LIMIT 20',
            ['q' => $validated['q']],
        );
    }

    public function neighbours(Request $request): JsonResponse
    {
        $validated = $request->validate(['id' => ['required', 'string', 'max:100']]);

        return $this->respond(
            'MATCH (n) WHERE elementId(n) = $id OPTIONAL MATCH (n)-[r]-(m) '
            .'WITH n, r, m LIMIT $limit RETURN n, r, m',
            ['id' => $validated['id'], 'limit' => self::NEIGHBOUR_LIMIT],
        );
    }

    public function cypher(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:'.CypherGuard::MAX_LENGTH],
        ]);

        try {
            $this->guard->assertReadOnly($validated['query']);
        } catch (KnowledgeGraphException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->respond($validated['query']);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function respond(string $cypher, array $parameters = []): JsonResponse
    {
        try {
            return response()->json($this->neo4j->read($cypher, $parameters));
        } catch (KnowledgeGraphException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    /**
     * @return list<array{key: string, title: string, html: string|null}>
     */
    private function reports(): array
    {
        $directory = rtrim((string) config('knowledge_graph.reports_path'), '/');
        $reports = [];

        foreach ((array) config('knowledge_graph.reports') as $key => $title) {
            $path = "{$directory}/{$key}.md";
            $markdown = $directory !== '' && is_readable($path) ? (string) file_get_contents($path) : null;

            $reports[] = [
                'key' => (string) $key,
                'title' => (string) $title,
                'html' => $markdown === null ? null : Str::markdown($markdown, [
                    'html_input' => 'escape',
                    'allow_unsafe_links' => false,
                ]),
            ];
        }

        return $reports;
    }
}
