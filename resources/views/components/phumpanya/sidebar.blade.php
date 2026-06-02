@props([
    'recentSearches' => collect(),
    'active' => 'search',
])

@php
    $user = auth()->user();
    $planLabel = $user->isAdmin() ? 'Admin' : 'KU Member';
@endphp

<aside class="flex w-64 shrink-0 flex-col border-r border-gray-200 bg-gray-50/80 px-4 py-6">
    <div class="mb-8 flex items-center gap-3 px-2">
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
        </div>
        <div class="min-w-0">
            <p class="truncate text-xs text-gray-500">{{ $planLabel }}</p>
            <p class="truncate text-sm font-medium text-gray-900">{{ $user->name }}</p>
        </div>
    </div>

    <nav class="space-y-1 text-sm">
        <a
            href="{{ route('search.index') }}"
            @class([
                'flex items-center gap-2 rounded-lg px-3 py-2 font-medium transition',
                'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200' => $active === 'search',
                'text-gray-600 hover:bg-white/60 hover:text-gray-900' => $active !== 'search',
            ])
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            Search
        </a>
        <a
            href="{{ route('smart-picks.index') }}"
            @class([
                'flex items-center gap-2 rounded-lg px-3 py-2 font-medium transition',
                'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200' => $active === 'smart-picks',
                'text-gray-600 hover:bg-white/60 hover:text-gray-900' => $active !== 'smart-picks',
            ])
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 7l9-5-9-5-9 5 9 5z"/></svg>
            Smart Picks
        </a>
        <a
            href="{{ route('learning.show') }}"
            @class([
                'flex items-center gap-2 rounded-lg px-3 py-2 font-medium transition',
                'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200' => $active === 'learning',
                'text-gray-600 hover:bg-white/60 hover:text-gray-900' => $active !== 'learning',
            ])
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6m6 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            My Progress
        </a>
    </nav>

    <div class="mt-8 flex items-center justify-between px-2">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Recents</h2>
        <a href="{{ route('search.index') }}" class="text-gray-400 hover:text-phumpanya-900" title="New search">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </a>
    </div>

    <ul class="mt-2 flex-1 space-y-0.5 overflow-y-auto text-sm">
        @forelse ($recentSearches as $recent)
            <li>
                <a
                    href="{{ route('search.show', $recent) }}"
                    class="block truncate rounded-md px-2 py-1.5 text-gray-600 hover:bg-white hover:text-gray-900"
                    title="{{ $recent->query }}"
                >
                    {{ $recent->query }}
                </a>
            </li>
        @empty
            <li class="px-2 py-1.5 text-xs text-gray-400">No searches yet</li>
        @endforelse
    </ul>

    <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <p class="text-xs font-medium text-gray-900">Let's start!</p>
        <p class="mt-1 text-xs text-gray-500">Sourcing or organizing new studies couldn't be easier</p>
        <a
            href="{{ route('search.index') }}"
            class="mt-3 flex w-full items-center justify-center rounded-lg bg-phumpanya-900 px-3 py-2 text-xs font-semibold text-white hover:bg-phumpanya-800"
        >
            + Search
        </a>
    </div>

    <div class="mt-4 border-t border-gray-200 pt-4 text-xs">
        <a href="{{ route('profile.edit') }}" class="text-gray-500 hover:text-phumpanya-900">Profile</a>
        <form method="POST" action="{{ route('logout') }}" class="mt-2">
            @csrf
            <button type="submit" class="text-gray-500 hover:text-phumpanya-900">Log out</button>
        </form>
    </div>
</aside>
