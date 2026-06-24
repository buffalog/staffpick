<meta name="twitter:title" content="{{ !empty($title) ? $title : config('app.name', 'StaffPick') }}" />
<meta name="twitter:description" content="{{ !empty($description) ? $description : config('app.description', 'StaffPick') }}" />

@if (config('open-graphy.enabled', false) && !empty($title) && !request()->routeIs('home'))
    @if (!empty($socialCard))
        <x-open-graphy::links title="{{ $title }}" image="{{ $socialCard }}"/>
    @else
        <x-open-graphy::links title="{{ $title }}"/>
    @endif
@else
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="{{!empty($socialCard) ? $socialCard : asset('images/twitter-card.png')}}" />
    <meta property="og:image" content="{{ !empty($socialCard) ? $socialCard : asset('images/facebook-card.png')}}" />
@endif

<meta property="og:title" content="{{ !empty($title) ? $title : config('app.name', 'StaffPick') }}" />
<meta property="og:url" content="{{route('home')}}" />

<meta property="og:description" content="{{ !empty($description) ? $description : config('app.description', 'StaffPick') }}" />
