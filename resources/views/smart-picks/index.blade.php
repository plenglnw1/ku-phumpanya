@php
    $picks = $smartPicks['picks'] ?? [];
    $filters = $smartPicks['filters'] ?? [];
@endphp

<x-layouts.phumpanya :recent-searches="$recentSearches" :active-nav="'smart-picks'" title="AI Recommendations">
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold text-gray-900">AI Recommendations for You</h1>
        <p class="mt-1 text-sm text-gray-500">Based on your interests, progress, and BCG Ontology connections.</p>

        <div class="mt-6 flex gap-3 rounded-xl border border-phumpanya-100 bg-phumpanya-50/50 p-4">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-phumpanya-900 text-xs font-bold text-white">i</span>
            <p class="text-sm text-gray-700">{{ $smartPicks['explanation'] ?? '' }}</p>
        </div>

        <h2 class="mt-10 text-xs font-semibold uppercase tracking-wide text-gray-500">Top Picks</h2>
        <div class="mt-4 space-y-4">
            @foreach ($picks as $pick)
                <article @class([
                    'rounded-2xl border bg-white p-5 shadow-sm',
                    'border-phumpanya-900 ring-1 ring-phumpanya-900/10' => $pick['featured'] ?? false,
                    'border-gray-200' => ! ($pick['featured'] ?? false),
                ])>
                    @if ($pick['featured'] ?? false)
                        <span class="inline-block rounded-full bg-phumpanya-900 px-2 py-0.5 text-xs font-semibold text-white">Best Match · {{ $pick['match'] }}%</span>
                    @endif
                    <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ $pick['title'] }}
                                @if (! ($pick['featured'] ?? false))
                                    <span class="ml-2 text-sm font-normal text-gray-400">{{ $pick['match'] }}%</span>
                                @endif
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $pick['meta'] }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($pick['tags'] as $tag)
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-700">{{ $tag }}</span>
                                @endforeach
                            </div>
                        </div>
                        @if ($pick['featured'] ?? false)
                            <a href="{{ route('learning.show') }}" class="shrink-0 rounded-lg bg-phumpanya-900 px-4 py-2 text-sm font-semibold text-white hover:bg-phumpanya-800">Start Path</a>
                        @else
                            <a href="{{ route('learning.show') }}" class="shrink-0 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">View</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <h2 class="mt-10 text-xs font-semibold uppercase tracking-wide text-gray-500">Refine Recommendations</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($filters as $index => $filter)
                <button
                    type="button"
                    @class([
                        'rounded-full px-4 py-2 text-sm font-medium',
                        'bg-phumpanya-900 text-white' => $index === 0,
                        'border border-gray-200 bg-white text-gray-700' => $index !== 0,
                    ])
                    disabled
                >+ {{ $filter }}</button>
            @endforeach
        </div>
    </div>
</x-layouts.phumpanya>
