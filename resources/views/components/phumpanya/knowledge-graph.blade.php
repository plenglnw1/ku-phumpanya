@props(['graph'])

@php
    use Illuminate\Support\Str;
    $center = $graph['center'] ?? ['label' => 'Topic', 'color' => '#2D5A43'];
    $nodes = $graph['nodes'] ?? [];
    $edges = $graph['edges'] ?? [];
    $nodeTypes = collect($nodes)->pluck('type')->filter()->unique()->values()->all();
    $count = max(count($nodes), 1);
    $radius = 120;
    $cx = 200;
    $cy = 160;
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

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-gray-50/50 p-4">
        <svg viewBox="0 0 400 320" class="mx-auto w-full max-w-lg" role="img" aria-label="Knowledge graph visualization">
            @foreach ($nodes as $index => $node)
                @php
                    $angle = (2 * M_PI * $index / $count) - M_PI / 2;
                    $nx = $cx + $radius * cos($angle);
                    $ny = $cy + $radius * sin($angle);
                @endphp
                <line
                    x1="{{ $cx }}" y1="{{ $cy }}"
                    x2="{{ $nx }}" y2="{{ $ny }}"
                    stroke="#d1d5db" stroke-width="1.5" stroke-dasharray="4 4"
                />
            @endforeach

            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="36" fill="{{ $center['color'] }}" />
            <text x="{{ $cx }}" y="{{ $cy }}" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="9" font-weight="600">
                {{ Str::limit($center['label'], 14) }}
            </text>

            @foreach ($nodes as $index => $node)
                @php
                    $angle = (2 * M_PI * $index / $count) - M_PI / 2;
                    $nx = $cx + $radius * cos($angle);
                    $ny = $cy + $radius * sin($angle);
                @endphp
                <circle cx="{{ $nx }}" cy="{{ $ny }}" r="28" fill="{{ $node['color'] }}" />
                <text x="{{ $nx }}" y="{{ $ny }}" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="7" font-weight="500">
                    {{ Str::limit($node['label'], 12) }}
                </text>
            @endforeach
        </svg>
    </div>

    @if (count($edges) > 0)
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="text-sm font-semibold text-gray-900">Researcher connections (edges)</h3>
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
