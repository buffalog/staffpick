@php
    use App\Models\StaffPick\TenantConfig;

    $providerLabel = TenantConfig::entityLabel('provider', __('Provider'));
    $disciplineLabel = TenantConfig::entityLabel('discipline', __('Discipline'));
    $subjectHasCoordinates = $record->subject && $record->subject->latitude !== null && $record->subject->longitude !== null;
@endphp

<div class="space-y-4 text-sm">
    <p class="text-gray-500 dark:text-gray-400">
        {{ __('Ranked by tier, then proximity. :discipline match and provider radius are required; specialty and language matches add bonus score.', ['discipline' => $disciplineLabel]) }}
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
                        <th class="px-3 py-2">{{ __('Tier') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Distance') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Score') }}</th>
                        <th class="px-3 py-2">{{ __('Factors') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($results as $index => $result)
                        @php($provider = $result->provider)
                        <tr class="text-gray-700 dark:text-gray-200">
                            <td class="px-3 py-2 text-gray-400">{{ $index + 1 }}</td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ trim("{$provider->first_name} {$provider->last_name}") }}
                                </div>
                                @if ($provider->business_name)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $provider->business_name }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $provider->tier?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($result->distanceMiles, 1) }} {{ __('mi') }}</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ number_format($result->score, 3) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-1">
                                    @if (data_get($result->factors, 'near_miss'))
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-400/10 dark:text-amber-400">{{ __('Near miss') }}</span>
                                    @endif
                                    @if (data_get($result->factors, 'specialty') > 0)
                                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-400/10 dark:text-blue-400">{{ __('Specialty') }}</span>
                                    @endif
                                    @if (data_get($result->factors, 'language'))
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-400/10 dark:text-green-400">{{ __('Language') }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
