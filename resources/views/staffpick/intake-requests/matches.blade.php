@php
    use App\Models\StaffPick\TenantConfig;

    $providerLabel = TenantConfig::entityLabel('provider', __('Provider'));
    $disciplineLabel = TenantConfig::entityLabel('discipline', __('Discipline'));
    $disciplineName = $record->discipline?->name;
    $subjectHasCoordinates = $record->subject && $record->subject->latitude !== null && $record->subject->longitude !== null;
    $languageWarning = $results->isNotEmpty() && $results->first()->languageWarning;
@endphp

<div class="space-y-4 text-sm">
    @if ($languageWarning)
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300">
            <span class="font-semibold">{{ __('Language warning:') }}</span>
            {{ __('no eligible :provider speaks the requested language (:language).', [
                'provider' => mb_strtolower($providerLabel),
                'language' => $record->subject?->language_preference,
            ]) }}
            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300/80">
                {{ __('No providers in the match pool speak the patient\'s preferred language. Consider expanding the radius or adjusting the language preference.') }}
            </p>
        </div>
    @endif

    <p class="text-gray-500 dark:text-gray-400">
        {{ __('Preferred :providers first, then by tier, then by score (language match + proximity).', [
            'providers' => mb_strtolower(\Illuminate\Support\Str::plural($providerLabel)),
        ]) }}
    </p>

    @if ($results->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-gray-500 dark:border-gray-700 dark:text-gray-400">
            @unless ($subjectHasCoordinates)
                {{ __('This case has no geocoded address, so distance cannot be calculated. Add latitude/longitude to the subject to enable matching.') }}
            @else
                {{ __('No eligible :providers found in range for this case.', ['providers' => \Illuminate\Support\Str::plural($providerLabel)]) }}
            @endunless
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">{{ $providerLabel }}</th>
                        <th class="px-3 py-2">{{ $disciplineLabel }}</th>
                        <th class="px-3 py-2">{{ __('Tier') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Distance') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Score') }}</th>
                        <th class="px-3 py-2 text-center">{{ __('Language') }}</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($results as $index => $result)
                        @php($provider = $result->provider)
                        <tr class="text-gray-700 dark:text-gray-200">
                            <td class="px-3 py-2 text-gray-400">{{ $index + 1 }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ trim("{$provider->first_name} {$provider->last_name}") }}</span>
                                    @if (data_get($result->factors, 'is_preferred'))
                                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400">{{ __('Preferred') }}</span>
                                    @endif
                                </div>
                                @if ($provider->business_name)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $provider->business_name }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $disciplineName ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $provider->tier?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($result->distanceMiles, 1) }} {{ __('mi') }}</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ number_format($result->score, 3) }}</td>
                            <td class="px-3 py-2 text-center">
                                @if ($result->languageMatched)
                                    <span class="font-semibold text-green-600 dark:text-green-400" title="{{ __('Language match') }}">&checkmark;</span>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button
                                    type="button"
                                    wire:click="assignProvider({{ $record->id }}, {{ $provider->id }})"
                                    wire:loading.attr="disabled"
                                    class="rounded-md bg-primary-600 px-3 py-1 text-xs font-medium text-white hover:bg-primary-500 disabled:opacity-50"
                                >
                                    {{ __('Assign') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
