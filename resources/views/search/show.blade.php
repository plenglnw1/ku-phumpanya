@php
    $overview = $topic['overview'] ?? [];
    $graph = $topic['knowledge_graph'] ?? [];
    $path = $topic['learning_path'] ?? [];
    $evidence = $evidence ?? ($topic['evidence'] ?? []);
    $title = $topic['title'] ?? $searchHistory->query;
@endphp

<x-layouts.phumpanya :recent-searches="$recentSearches" :active-nav="'search'" :title="$title">
    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-500">GraphRAG result</span>
                @if (! empty($topic['tier']))
                    <span @class([
                        'rounded-full border px-3 py-1 text-xs font-medium uppercase',
                        'border-green-200 bg-green-50 text-green-800' => $topic['tier'] === 'basic',
                        'border-blue-200 bg-blue-50 text-blue-800' => $topic['tier'] === 'intermediate',
                        'border-purple-200 bg-purple-50 text-purple-800' => $topic['tier'] === 'advanced',
                    ])>{{ $topic['tier'] }} tier</span>
                @endif
            </div>
        </div>

        <div class="mb-6 inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm font-medium">
            <a
                href="{{ route('search.show', ['searchHistory' => $searchHistory, 'tab' => 'overview']) }}"
                @class([
                    'rounded-md px-4 py-2 transition',
                    'bg-phumpanya-900 text-white shadow-sm' => $tab === 'overview',
                    'text-gray-600 hover:text-gray-900' => $tab !== 'overview',
                ])
            >Overview</a>
            <a
                href="{{ route('search.show', ['searchHistory' => $searchHistory, 'tab' => 'graph']) }}"
                @class([
                    'rounded-md px-4 py-2 transition',
                    'bg-phumpanya-900 text-white shadow-sm' => $tab === 'graph',
                    'text-gray-600 hover:text-gray-900' => $tab !== 'graph',
                ])
            >Knowledge Graph</a>
            <a
                href="{{ route('search.show', ['searchHistory' => $searchHistory, 'tab' => 'learning']) }}"
                @class([
                    'rounded-md px-4 py-2 transition',
                    'bg-phumpanya-900 text-white shadow-sm' => $tab === 'learning',
                    'text-gray-600 hover:text-gray-900' => $tab !== 'learning',
                ])
            >Learning Path</a>
        </div>

        @if ($tab === 'overview')
            <div class="prose prose-sm max-w-none space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm text-gray-700">
                <p>{{ $overview['intro'] ?? '' }}</p>
                <p class="italic text-gray-600">{{ $overview['analogy'] ?? '' }}</p>
                <p><strong>{{ $overview['research_basis'] ?? '' }}</strong></p>
                <p>{{ $overview['expert'] ?? '' }}</p>
            </div>

            <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">Evidence from KU sources</h3>
                <ul class="mt-3 space-y-3">
                    @forelse ($evidence as $item)
                        <li class="rounded-lg border border-gray-100 p-3">
                            <p class="text-sm font-medium text-gray-900">{{ $item['title'] }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $item['source'] }}</p>
                            <p class="mt-2 text-sm text-gray-600">{{ $item['snippet'] }}</p>
                            @if (! empty($item['url']))
                                <a href="{{ $item['url'] }}" target="_blank" class="mt-2 inline-block text-xs font-medium text-phumpanya-900 hover:underline">Open source URL</a>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">No evidence available.</li>
                    @endforelse
                </ul>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <a
                    href="{{ route('search.show', ['searchHistory' => $searchHistory, 'tab' => 'graph']) }}"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-phumpanya-900/30"
                >
                    <h3 class="font-semibold text-gray-900">Knowledge Graph</h3>
                    <p class="mt-1 text-sm text-gray-500">See how researchers connect to sub-topics</p>
                </a>
                <a
                    href="{{ route('search.show', ['searchHistory' => $searchHistory, 'tab' => 'learning']) }}"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-phumpanya-900/30"
                >
                    <h3 class="font-semibold text-gray-900">Learning Path</h3>
                    <p class="mt-1 text-sm text-gray-500">Track phases and estimated hours</p>
                </a>
            </div>
        @elseif ($tab === 'graph')
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">Knowledge Graph</h2>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600" disabled>Filter</button>
                        <button type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600" disabled>Zoom</button>
                    </div>
                </div>
                <x-phumpanya.knowledge-graph :graph="$graph" />
            </div>
        @else
            <x-phumpanya.learning-path :path="$path" :topic-title="$title" />
        @endif
    </div>
</x-layouts.phumpanya>
