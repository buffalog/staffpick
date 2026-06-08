<li {{ $attributes->merge(['class' => 'inline-flex gap-2']) }}>
    <span class="p-1 rounded-full h-6 w-6 text-center mx-auto text-primary-500">@svg('check', 'stroke-black')</span>
    <span>{{ $slot }}</span>
</li>
