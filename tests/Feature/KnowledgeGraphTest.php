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
        file_put_contents("{$directory}/owl-validation.md", "# OWL validation\n\n| Check | Result |\n|---|---|\n| TBox | PASS |\n\n<script>alert(1)</script>\n");
        config()->set('knowledge_graph.reports_path', $directory);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('graph.index'))
            ->assertOk()
            ->assertSee('<td>PASS</td>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('waiting for the three experts', false);

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
            ->assertJsonPath('nodes.0.name', 'Forestry')
            ->assertJsonPath('nodes.0.uid', 'faculty:วนศาสตร์')
            ->assertJsonMissingPath('nodes.0.properties.content_hash')
            ->assertJsonPath('edges.0.type', 'mitigatesCarbonVia')
            ->assertJsonPath('edges.0.from', '1');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Access-Mode', 'READ')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('neo4j:secret')));
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
                    'row' => [['name' => 'Forestry'], ['via' => 'carbon_stock'], ['name' => 'Carbon Footprint']],
                    'graph' => [
                        'nodes' => [
                            ['id' => '1', 'labels' => ['Faculty'], 'properties' => ['uid' => 'faculty:วนศาสตร์', 'name' => 'Forestry', 'content_hash' => 'abc']],
                            ['id' => '2', 'labels' => ['Topic'], 'properties' => ['uid' => 'topic:4', 'name' => 'Carbon Footprint']],
                        ],
                        'relationships' => [
                            ['id' => '9', 'type' => 'mitigatesCarbonVia', 'startNode' => '1', 'endNode' => '2', 'properties' => []],
                        ],
                    ],
                ]],
            ]],
            'errors' => [],
        ];
    }
}
