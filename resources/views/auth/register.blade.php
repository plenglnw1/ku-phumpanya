<x-guest-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Create account</h1>
            <p class="mt-2 text-sm text-gray-600">
                Public email registration is disabled. Sign in with Google, then complete your KU profile.
            </p>
        </div>

        <a
            href="{{ route('auth.google') }}"
            class="flex w-full items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white px-6 py-3 text-center text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
        >
            Sign in with Google
        </a>

        <p class="text-center text-sm text-gray-600">
            <a href="{{ route('welcome') }}" class="text-phumpanya-900 hover:underline">&larr; Back to home</a>
        </p>
    </div>
</x-guest-layout>
