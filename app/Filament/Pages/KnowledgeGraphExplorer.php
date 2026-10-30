<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\SearchHistory;
use App\Services\Admin\GraphBrowser;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

class KnowledgeGraphExplorer extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static string|UnitEnum|null $navigationGroup = 'Knowledge';

    protected static ?string $navigationLabel = 'Knowledge Graph';

    protected static ?string $title = 'Knowledge Graph';

    protected static ?string $slug = 'knowledge-graph';

    protected string $view = 'filament.pages.knowledge-graph-explorer';

    #[Url]
    public ?string $topic = '4';

    #[Url]
    public ?string $source = null;

    #[Url]
    public ?string $predicate = null;

    #[Url]
    public ?int $history = null;

    public ?string $selected = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['topic', 'source', 'predicate'], true)) {
            $this->history = null;
            $this->selected = null;
        }
    }

    public function selectNode(string $id): void
    {
        $this->selected = $id;
    }

    public function closeReplay(): void
    {
        $this->history = null;
        $this->selected = null;
    }

    #[Computed]
    public function replayRecord(): ?SearchHistory
    {
        return $this->history === null
            ? null
            : SearchHistory::query()->with('user:id,name,email')->find($this->history);
    }

    /**
     * @return array{nodes: list<array<string, string>>, edges: list<array<string, string>>, note: ?string, evidence?: list<array<string, string>>}
     */
    #[Computed]
    public function graph(): array
    {
        $browser = app(GraphBrowser::class);

        if ($this->replayRecord instanceof SearchHistory) {
            return $browser->replay($this->replayRecord);
        }

        return $browser->browse($this->blankToNull($this->topic), $this->blankToNull($this->source), $this->blankToNull($this->predicate));
    }

    /**
     * @return array{node: ?array<string, string>, documents: list<array<string, string>>}
     */
    #[Computed]
    public function panel(): array
    {
        if ($this->selected === null) {
            return ['node' => null, 'documents' => []];
        }

        $node = collect($this->graph['nodes'])->firstWhere('id', $this->selected);
        if ($node === null || ($node['type'] ?? '') === 'document') {
            return ['node' => $node, 'documents' => []];
        }

        return [
            'node' => $node,
            'documents' => app(GraphBrowser::class)->documentsFor($node['label'], $this->blankToNull($this->source)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'topics' => (array) config('knowledge_graph.topics'),
            'sources' => (array) config('knowledge_graph.source_labels'),
            'predicates' => (array) config('knowledge_graph.predicate_labels'),
        ];
    }

    private function blankToNull(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
