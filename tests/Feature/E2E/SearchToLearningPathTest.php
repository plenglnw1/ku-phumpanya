<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Gate 1.5.1 at the API level: search → result → stored history → learning-path
 * progress, for every BCG topic and pilot faculty. The browser-level run of the
 * same journey lives in KU-BCG (Playwright).
 */
class SearchToLearningPathTest extends TestCase
{
    use RefreshDatabase;

    private const TOPICS = [
        'ชีวมวลและพลังงานทดแทน',
        'ไคโตซานและบรรจุภัณฑ์ชีวภาพ',
        'microplastics water quality',
        'carbon footprint ภาคเกษตร',
        'การเพาะเลี้ยงสัตว์น้ำที่ยั่งยืน probiotic',
        'วนเกษตร agroforestry ความหลากหลายทางชีวภาพ',
    ];

    private const FACULTIES = ['เกษตร', 'อุตสาหกรรมเกษตร', 'วนศาสตร์', 'ประมง'];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('gemini.enabled', false);
        config()->set('qdrant.enabled', false);
        config()->set('elasticsearch.enabled', false);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function journeys(): iterable
    {
        foreach (self::FACULTIES as $faculty) {
            foreach (self::TOPICS as $topic) {
                yield "{$faculty} / {$topic}" => [$faculty, $topic];
            }
        }
    }

    #[DataProvider('journeys')]
    public function test_happy_path(string $faculty, string $topic): void
    {
        $user = User::factory()->create(['role' => UserRole::Student, 'faculty' => $faculty]);
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/search', ['query' => $topic])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'result' => [
                'title', 'overview', 'knowledge_graph' => ['nodes', 'edges'], 'learning_path' => ['phases'], 'evidence', 'tags',
            ]]])
            ->json('data.id');

        $result = $this->getJson("/api/search/history/{$id}")
            ->assertOk()
            ->assertJsonPath('data.query', $topic)
            ->json('data.result');

        $this->getJson('/api/search/recent')->assertOk()->assertJsonPath('data.0.id', $id);

        $phases = $result['learning_path']['phases'];
        $this->assertNotEmpty($phases, 'learning path has phases');
        $phase = array_key_first(array_filter($phases, fn (array $p): bool => ! empty($p['modules'])));
        $this->assertNotNull($phase, 'learning path has a module');

        $this->putJson("/api/search/history/{$id}/progress", ['phase' => $phase, 'card' => 0, 'completed' => true])
            ->assertOk()
            ->assertJsonPath('data.completed_count', 1);

        $this->getJson('/api/progress')->assertOk()->assertJsonPath('summary.completed_count', 1);
    }
}
