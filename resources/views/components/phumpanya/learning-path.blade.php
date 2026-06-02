@props(['path', 'topicTitle' => ''])

@php
    $progress = (int) ($path['progress'] ?? 0);
@endphp

<div class="space-y-8">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-3xl font-semibold text-gray-900">~{{ $path['estimated_hours'] ?? '100' }} hrs</p>
                <p class="mt-1 text-sm text-gray-500">{{ $path['subtitle'] ?? 'Total estimated time' }}</p>
            </div>
            @if ($topicTitle)
                <span class="rounded-full border border-gray-200 bg-gray-50 px-4 py-1.5 text-sm text-gray-700">{{ $topicTitle }}</span>
            @endif
        </div>
        <div class="mt-6 h-2 overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full bg-phumpanya-900 transition-all" style="width: {{ $progress }}%"></div>
        </div>
        <p class="mt-2 text-xs text-gray-500">{{ $progress }}% complete (mock progress)</p>
    </div>

    @foreach ($path['phases'] ?? [] as $phase)
        <section class="space-y-4">
            <div>
                <span class="inline-block rounded-md bg-phumpanya-900 px-2 py-0.5 text-xs font-semibold text-white">{{ $phase['name'] }}</span>
                <p class="mt-2 text-sm text-gray-600">{{ $phase['intro'] }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($phase['modules'] ?? [] as $module)
                    <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h4 class="font-semibold text-gray-900">{{ $module['title'] }}</h4>
                        <p class="mt-1 text-xs font-medium text-phumpanya-900">{{ $module['hours'] }}</p>
                        <p class="mt-2 text-sm text-gray-500">{{ $module['desc'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
