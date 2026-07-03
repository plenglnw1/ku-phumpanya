@props(['graph'])

@php
    $center = $graph['center'] ?? ['label' => 'Topic', 'color' => '#2D5A43', 'type' => 'topic'];
    $nodes = $graph['nodes'] ?? [];
    $edges = $graph['edges'] ?? [];
    $nodeTypes = collect($nodes)->pluck('type')->filter()->unique()->values()->all();
    $graphPayload = json_encode([
        'center' => $center,
        'nodes' => $nodes,
        'edges' => $edges,
        'description' => $graph['description'] ?? '',
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
@endphp

<div class="space-y-6">
    <p class="text-sm text-gray-600">{{ $graph['description'] ?? '' }}</p>

    @if (count($nodeTypes) > 0)
        <div class="flex flex-wrap items-center gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Node types:</p>
            @foreach ($nodeTypes as $nodeType)
                <span class="rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-600">{{ $nodeType }}</span>
            @endforeach
        </div>
    @endif

    <div
        class="overflow-x-auto rounded-xl border border-gray-200 bg-gray-50/50 p-4"
        data-knowledge-graph="{{ $graphPayload }}"
    >
        <svg class="mx-auto w-full max-w-xl min-h-[360px]" role="img" aria-label="Knowledge graph visualization"></svg>
    </div>

    @if (count($edges) > 0)
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="text-sm font-semibold text-gray-900">Relation triples</h3>
            <ul class="mt-3 space-y-2">
                @foreach ($edges as $edge)
                    <li class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                        <span class="font-medium text-gray-900">{{ $edge['from'] }}</span>
                        <span class="rounded-full bg-phumpanya-900/10 px-2 py-0.5 text-xs font-medium text-phumpanya-900">{{ $edge['type'] }}</span>
                        <span aria-hidden="true">→</span>
                        <span class="font-medium text-gray-900">{{ $edge['to'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
