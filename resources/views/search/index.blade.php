<x-layouts.phumpanya :recent-searches="$recentSearches" :active-nav="'search'" title="Search">
    <div class="mx-auto flex max-w-3xl flex-col items-center justify-center py-8 lg:py-16">
        <div class="mb-10 flex items-center gap-3 text-center">
            <svg class="h-10 w-10 text-phumpanya-900" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8H21l-5.5 4 2.1 6.8L12 15.6 6.4 19.6l2.1-6.8L3 8.8h6.8L12 2z"/></svg>
            <h1 class="font-serif text-3xl font-semibold text-gray-900 sm:text-4xl">Start your next discovery.</h1>
        </div>

        <form method="POST" action="{{ route('search.store') }}" class="w-full" data-search-form>
            @csrf
            <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch">
                <input
                    type="text"
                    name="query"
                    value="{{ old('query') }}"
                    required
                    autofocus
                    placeholder="What shall we explore today?"
                    class="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-5 py-4 text-base text-gray-900 placeholder:text-gray-400 focus:border-phumpanya-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-phumpanya-900/20"
                />
                <button
                    type="submit"
                    data-search-submit
                    class="rounded-xl bg-phumpanya-900 px-8 py-4 text-sm font-semibold text-white shadow-sm hover:bg-phumpanya-800 focus:outline-none focus:ring-2 focus:ring-phumpanya-900 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"
                >
                    Search Now
                </button>
            </div>
            @error('query')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </form>

        <div class="mt-8 flex w-full flex-wrap justify-center gap-3">
            @foreach ($suggestions as $suggestion)
                <form method="POST" action="{{ route('search.store') }}" class="inline">
                    @csrf
                    <input type="hidden" name="query" value="{{ $suggestion }}">
                    <button
                        type="submit"
                        class="rounded-full border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 transition hover:border-phumpanya-900/30 hover:bg-gray-50"
                    >
                        {{ $suggestion }}
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</x-layouts.phumpanya>
