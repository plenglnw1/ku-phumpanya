<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\SearchHistory;
use App\Models\User;
use App\Services\GraphRag\ElasticClientFactory;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmartPicksApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array<string, mixed>>  $docs
     */
    private function fakeElasticsearch(array $docs): void
    {
        $body = json_encode(['hits' => ['hits' => array_map(
            static fn (array $doc, int $i): array => ['_score' => 10 - $i, '_source' => $doc],
            $docs,
            array_keys($docs),
        )]]);

        $client = ClientBuilder::create()
            ->setHttpClient(new GuzzleClient(['handler' => HandlerStack::create(new MockHandler([
                new Response(200, ['X-Elastic-Product' => 'Elasticsearch', 'Content-Type' => 'application/json'], $body),
            ]))]))
            ->build();

        $this->app->instance(ElasticClientFactory::class, new class($client) extends ElasticClientFactory
        {
            public function __construct(private readonly Client $client) {}

            public function make(): ?Client
            {
                return $this->client;
            }
        });
    }

    public function test_empty_index_returns_the_envelope_with_no_items(): void
    {
        config()->set('elasticsearch.enabled', false);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/smart-picks')
            ->assertOk()
            ->assertJsonPath('smart_picks.headline', 'AI Recommendations')
            ->assertJsonPath('smart_picks.items', []);
    }

    public function test_ranks_corpus_documents_toward_the_users_searches(): void
    {
        $user = User::factory()->create();
        SearchHistory::query()->create([
            'user_id' => $user->id,
            'query' => 'ไคโตซาน บรรจุภัณฑ์',
            'result' => ['title' => 'ไคโตซาน', 'evidence' => []],
        ]);
        $this->fakeElasticsearch([
            ['title' => 'ฟิล์มไคโตซานสำหรับบรรจุภัณฑ์', 'source' => 'KUKR', 'url' => 'https://kukr.test/1', 'topic_names_th' => ['บรรจุภัณฑ์'], 'keywords' => ['ไคโตซาน'], 'year' => 2024, 'bcg_pillars' => ['Bio']],
            ['title' => 'การปลูกป่า', 'source' => 'KU_Forest', 'url' => 'https://forest.test/2', 'topic_names_th' => ['ป่าไม้'], 'keywords' => [], 'year' => 2015, 'bcg_pillars' => ['Green']],
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/smart-picks')
            ->assertOk()
            ->assertJsonStructure(['smart_picks' => [
                'headline', 'reason', 'generated_at',
                'items' => ['*' => ['title', 'info', 'source', 'match', 'reasons', 'keywords', 'url', 'query']],
            ]])
            ->assertJsonPath('smart_picks.items.0.title', 'ฟิล์มไคโตซานสำหรับบรรจุภัณฑ์')
            ->assertJsonPath('smart_picks.items.0.source', 'KUKR');
    }
}
