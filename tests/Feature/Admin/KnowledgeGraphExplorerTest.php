<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Pages\KnowledgeGraphExplorer;
use App\Models\SearchHistory;
use App\Models\User;
use App\Services\Admin\GraphBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KnowledgeGraphExplorerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_explorer_without_elasticsearch(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(KnowledgeGraphExplorer::getUrl())
            ->assertOk()
            ->assertSee('เลือกสิ่งที่อยากดู')
            ->assertSee('Elasticsearch');
    }

    public function test_replay_shows_the_stored_graph_with_thai_predicates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $history = $this->historyWithGraph($admin);

        Livewire::actingAs($admin)
            ->test(KnowledgeGraphExplorer::class, ['history' => $history->id])
            ->assertSee('กราฟของการค้นหา #'.$history->id)
            ->assertSee('ลดคาร์บอน')
            ->assertSee('Carbon credit guide')
            ->assertSee('ช่วยลดคาร์บอนผ่าน');
    }

    public function test_replay_converts_knowledge_graph_and_evidence(): void
    {
        $history = $this->historyWithGraph(User::factory()->create());

        $graph = app(GraphBrowser::class)->replay($history);

        $this->assertSame(['ลดคาร์บอน', 'ป่าไม้'], array_column($graph['nodes'], 'id'));
        $this->assertSame('ช่วยลดคาร์บอนผ่าน', $graph['edges'][0]['label']);
        $this->assertSame('KUKR', $graph['evidence'][0]['source']);
    }

    public function test_non_admin_cannot_open_explorer(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);

        $this->actingAs($student)
            ->get(KnowledgeGraphExplorer::getUrl())
            ->assertRedirect('/filament/login');
    }

    private function historyWithGraph(User $user): SearchHistory
    {
        return SearchHistory::create([
            'user_id' => $user->id,
            'query' => 'คาร์บอนเครดิต',
            'status' => 'success',
            'result' => [
                'knowledge_graph' => [
                    'center' => 'ลดคาร์บอน',
                    'nodes' => [
                        ['label' => 'ลดคาร์บอน', 'type' => 'topic'],
                        ['label' => 'ป่าไม้', 'type' => 'topic'],
                    ],
                    'edges' => [
                        ['from' => 'ป่าไม้', 'to' => 'ลดคาร์บอน', 'type' => 'mitigatesCarbonVia'],
                    ],
                ],
                'evidence' => [
                    ['title' => 'Carbon credit guide', 'source' => 'KUKR', 'url' => 'https://kukr.lib.ku.ac.th/1'],
                ],
            ],
        ]);
    }
}
