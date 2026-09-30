<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KnowledgeGraphTest extends TestCase
{
    use RefreshDatabase;

    private const NEO4J = 'http://neo4j.test:17474/db/neo4j/tx/commit';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('knowledge_graph.enabled', true);
        config()->set('knowledge_graph.http_url', 'http://neo4j.test:17474');
        config()->set('knowledge_graph.password', 'secret');
        config()->set('knowledge_graph.viewer_emails', ['expert@ku.th']);
        config()->set('knowledge_graph.reports_path', '');
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('graph.index'))->assertRedirect(route('login'));
    }

    public function test_student_outside_the_viewer_list_is_forbidden(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);

        $this->actingAs($student)->get(route('graph.index'))->assertForbidden();
        $this->actingAs($student)->getJson(route('graph.overview'))->assertForbidden();
    }

    public function test_admin_and_listed_viewer_can_open_the_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $expert = User::factory()->create(['role' => UserRole::Researcher, 'email' => 'Expert@KU.th']);

        $this->actingAs($admin)->get(route('graph.index'))->assertOk()->assertSee('BCG Knowledge Graph');
        $this->actingAs($expert)->get(route('graph.index'))->assertOk()->assertSee('Cypher (read-only)');
    }

    public function test_page_renders_published_reports(): void
    {
        $directory = sys_get_temp_dir().'/kg-reports-'.uniqid();
        mkdir($directory);
        file_put_contents("{$directory}/neo4j-verify.md", "# Neo4j gate verification\n\n| label | cnt |\n|---|---|\n| Topic | 6 |\n\n<script>alert(1)</script>\n");
        config()->set('knowledge_graph.reports_path', $directory);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('graph.index'))
            ->assertOk()
            ->assertSee('<td>Topic</td>', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        array_map('unlink', glob("{$directory}/*"));
        rmdir($directory);
    }

    public function test_overview_normalizes_the_neo4j_graph(): void
    {
        Http::fake([self::NEO4J => Http::response($this->graphResponse())]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->getJson(route('graph.overview'))
            ->assertOk()
            ->assertJsonCount(2, 'nodes')
            ->assertJsonPath('nodes.0.id', '4:db:1')
            ->assertJsonPath('nodes.0.name', 'Faculty_วนศาสตร์')
            ->assertJsonPath('nodes.1.name', 'Carbon Footprint & Carbon Neutrality')
            ->assertJsonPath('edges.0.type', 'mitigatesCarbonVia')
            ->assertJsonPath('edges.0.from', '4:db:1')
            ->assertJsonPath('edges.0.to', '4:db:2');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Access-Mode', 'READ')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('neo4j:secret')));
    }

    public function test_hub_and_neighbours_query_by_name_and_element_id(): void
    {
        Http::fake([self::NEO4J => Http::response($this->graphResponse())]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->getJson(route('graph.hub'))->assertOk();
        $this->actingAs($admin)->getJson(route('graph.neighbours', ['id' => '4:db:2']))->assertOk();
        $this->actingAs($admin)->getJson(route('graph.neighbours'))->assertUnprocessable();
        $this->actingAs($admin)->getJson(route('graph.search', ['q' => 'x']))->assertUnprocessable();

        $parameters = static fn (Request $request): array => (array) $request['statements'][0]['parameters'];

        Http::assertSent(fn (Request $request): bool => ($parameters($request)['name'] ?? null) === 'CarbonFootprint');
        Http::assertSent(fn (Request $request): bool => ($parameters($request)['id'] ?? null) === '4:db:2'
            && str_contains($request['statements'][0]['statement'], 'elementId(n) = $id'));
    }

    public function test_cypher_rejects_writes_and_procedures_without_calling_neo4j(): void
    {
        Http::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach ([
            'MATCH (n) DETACH DELETE n',
            'CREATE (n:X) RETURN n',
            "LOAD CSV FROM 'http://169.254.169.254/' AS row RETURN row",
            "CALL n10s.rdf.stream.fetch('http://example.com', 'Turtle')",
            'USE system SHOW USERS',
            'MATCH (n) RETURN n; MATCH (m) RETURN m',
            'MATCH (n) SET n.name = "x" RETURN n',
        ] as $query) {
            $this->actingAs($admin)
                ->postJson(route('graph.cypher'), ['query' => $query])
                ->assertStatus(422);
        }

        Http::assertNothingSent();
    }

    public function test_cypher_allows_keywords_inside_strings(): void
    {
        Http::fake([self::NEO4J => Http::response($this->graphResponse())]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->postJson(route('graph.cypher'), ['query' => "MATCH (n) WHERE n.name CONTAINS 'set' RETURN n"])
            ->assertOk()
            ->assertJsonPath('columns.0', 'a');
    }

    public function test_neo4j_errors_are_reported_as_bad_gateway(): void
    {
        Http::fake([self::NEO4J => Http::response([
            'results' => [],
            'errors' => [['code' => 'Neo.ClientError.Statement.SyntaxError', 'message' => 'Invalid input']],
        ])]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->postJson(route('graph.cypher'), ['query' => 'MATCH (n RETURN n'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Invalid input');
    }

    public function test_unconfigured_graph_answers_bad_gateway(): void
    {
        config()->set('knowledge_graph.password', '');
        Http::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('graph.index'))->assertOk()->assertSee('not configured');
        $this->actingAs($admin)->getJson(route('graph.overview'))->assertStatus(502);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function graphResponse(): array
    {
        return [
            'results' => [[
                'columns' => ['a', 'r', 'b'],
                'data' => [[
                    'row' => [['name' => 'Faculty_วนศาสตร์'], [], ['id' => '4', 'name_en' => 'Carbon Footprint & Carbon Neutrality']],
                    'graph' => [
                        'nodes' => [
                            ['id' => '1', 'elementId' => '4:db:1', 'labels' => ['Entity'], 'properties' => ['name' => 'Faculty_วนศาสตร์']],
                            ['id' => '2', 'elementId' => '4:db:2', 'labels' => ['Topic'], 'properties' => ['id' => '4', 'name_en' => 'Carbon Footprint & Carbon Neutrality', 'name_th' => 'คาร์บอนฟุตพริ้นท์']],
                        ],
                        'relationships' => [
                            ['id' => '9', 'elementId' => '5:db:9', 'type' => 'mitigatesCarbonVia', 'startNode' => '1', 'endNode' => '2',
                                'startNodeElementId' => '4:db:1', 'endNodeElementId' => '4:db:2', 'properties' => []],
                        ],
                    ],
                ]],
            ]],
            'errors' => [],
        ];
    }
}
