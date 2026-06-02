@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 '.$class]) }}>
    <svg class="h-6 w-6 text-phumpanya-900" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M12 2l2.2 6.8H21l-5.5 4 2.1 6.8L12 15.6 6.4 19.6l2.1-6.8L3 8.8h6.8L12 2z"/>
    </svg>
    <span class="font-serif text-xl font-semibold text-gray-900">Phumpanya</span>
</div>
