@php
    $path = $topic['learning_path'] ?? [];
    $title = $topic['title'] ?? 'Learning Path';
@endphp

<x-layouts.phumpanya :recent-searches="$recentSearches" :active-nav="'learning'" :title="$title">
    <div class="mx-auto max-w-4xl">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">My Progress</h1>
            <p class="mt-1 text-sm text-gray-500">ระบบติดตามการเรียน — mock data for UAT</p>
        </div>

        <x-phumpanya.learning-path :path="$path" :topic-title="$title" />

        @if ($searchHistory)
            <p class="mt-8 text-center text-sm text-gray-500">
                <a href="{{ route('search.show', $searchHistory) }}" class="text-phumpanya-900 hover:underline">← Back to search results</a>
            </p>
        @endif
    </div>
</x-layouts.phumpanya>
