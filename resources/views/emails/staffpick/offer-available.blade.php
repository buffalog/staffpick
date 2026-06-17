<x-mail::message>
# {{ __('New assignment offer') }}

{{ __('You have a new assignment offer. Sign in to review the full details and respond.') }}

- **{{ __('Discipline') }}:** {{ $offer->intakeRequest?->discipline?->name ?? __('Unspecified') }}
- **{{ __('Area') }}:** {{ $offer->intakeRequest?->subject?->city ?? __('Unspecified') }}
@if ($offer->intakeRequest?->start_date)
- **{{ __('Proposed start') }}:** {{ $offer->intakeRequest->start_date->format('M j, Y') }}
@endif

<x-mail::button :url="route('staffpick.offer.respond', ['token' => $offer->token])">
{{ __('Review & respond') }}
</x-mail::button>

{{ __('For the patient\'s privacy, full case details are only available after you sign in.') }}

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
</x-mail::message>
