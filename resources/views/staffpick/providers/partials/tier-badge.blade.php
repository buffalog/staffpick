{{-- Tier badge pill, using the tier's stored color. Prop: $tier (ProviderTier|null).
     The one source of truth for the tier badge — reused by the card grid and the
     provider detail header. --}}
@if ($tier)
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ring-gray-950/10"
          style="background-color: {{ $tier->color }}; color: #1f2937;">
        {{ $tier->name }}
    </span>
@endif
