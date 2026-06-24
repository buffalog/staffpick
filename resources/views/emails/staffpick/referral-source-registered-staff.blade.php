<x-mail::message>
# {{ __('New referral source registration') }}

{{ __(':contact at :agency registered as a referral source and needs review.', ['contact' => $source->contact_name, 'agency' => $source->name]) }}

- **{{ __('Phone') }}:** {{ $source->phone }}
- **{{ __('Email') }}:** {{ $source->email }}

<x-mail::button :url="route('filament.dashboard.resources.referral-sources.index', ['tenant' => $source->tenant->uuid])">
{{ __('Review referral sources') }}
</x-mail::button>

{{ __('Thank you,') }}<br>
{{ config('app.name') }}
</x-mail::message>
