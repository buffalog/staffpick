<x-mail::message>
# {{ __('How was your recent visit?') }}

{{ __('We would love your feedback on your recent visit. Please rate your experience from 1 (poor) to 5 (excellent).') }}

<x-mail::button :url="$survey->responseUrl()">
{{ __('Rate your visit') }}
</x-mail::button>

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
</x-mail::message>
