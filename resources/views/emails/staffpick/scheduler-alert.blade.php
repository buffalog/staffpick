<x-mail::message>
# {{ $heading }}

{{ $bodyText }}

@if ($url)
<x-mail::button :url="$url">
{{ __('Open case') }}
</x-mail::button>
@endif

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
</x-mail::message>
