<x-guest-layout>
    <div class="space-y-8">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Sign in</h1>
            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                Welcome! To access your personalized digital services, including course registration
                and library access, please log in with your official university account.
            </p>
        </div>

        <div class="space-y-4">
            <a
                href="{{ route('login') }}"
                class="flex w-full items-center justify-center rounded-lg bg-phumpanya-900 px-6 py-3.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-phumpanya-800 focus:outline-none focus:ring-2 focus:ring-phumpanya-900 focus:ring-offset-2"
            >
                KU ALL-Login
            </a>

            <p class="text-center text-xs text-gray-500">
                You will be redirected to the secure KU ALL authentication page
            </p>
        </div>

        <div class="flex items-center justify-between border-t border-gray-100 pt-6 text-sm">
            <p class="text-gray-600">
                Need an account?
                <a href="{{ route('register') }}" class="font-medium text-phumpanya-900 hover:underline">Sign up</a>
            </p>
        </div>
    </div>
</x-guest-layout>
