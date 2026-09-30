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
    private const CONCEPT_LABELS = ['Topic', 'Faculty', 'BCGPillar', 'Course', 'Keyword', 'Concept'];

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
            ['labels' => self::CONCEPT_LABELS],
        );
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return $this->respond(
            'MATCH (n) WHERE n.uid IS NOT NULL '
            .'AND (toLower(coalesce(n.name, "")) CONTAINS toLower($q) OR n.uid = $q) '
            .'RETURN n ORDER BY size(coalesce(n.name, "")) LIMIT 20',
            ['q' => $validated['q']],
        );
    }

    public function neighbours(Request $request): JsonResponse
    {
        $validated = $request->validate(['uid' => ['required', 'string', 'max:300']]);

        return $this->respond(
            'MATCH (n {uid: $uid}) OPTIONAL MATCH (n)-[r]-(m) '
            .'WITH n, r, m LIMIT $limit RETURN n, r, m',
            ['uid' => $validated['uid'], 'limit' => self::NEIGHBOUR_LIMIT],
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
