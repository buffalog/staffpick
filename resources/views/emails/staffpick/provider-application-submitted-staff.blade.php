<x-mail::message>
# New provider application

**{{ $application->fullName() }}** has submitted a provider application for review.

- **Email:** {{ $application->email }}
- **Discipline:** {{ $application->discipline ?? '—' }}
- **City:** {{ $application->city ?? '—' }}

Review it in the Applications queue under **Our Providers**.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
