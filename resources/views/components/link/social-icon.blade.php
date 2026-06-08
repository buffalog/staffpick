@props([
    'link' => '#',
    'name' => '',
    'title' => '',
])
<span>
    <a href="{{$link}}" {{ $attributes->merge(['class' => 'flex gap-2 justify-center items-center']) }} target="_blank">
        <span class="rounded-full border p-1">
            @svg($name)
        </span>
        <span>{{$title}}</span>
    </a>
</span>
