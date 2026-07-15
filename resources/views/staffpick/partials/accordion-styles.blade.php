{{-- Shared <x-sp-accordion> styling — ported literally from the mockup. Rendered once per
     page that uses accordions; the <x-sp-accordion> component only carries structure. Lives
     here (not inlined per page) so the provider and case View pages can't drift — visual
     parity between them is the whole point. The three surface/border color tokens are defined
     here (the app had no such vars); radius 0.75rem matches the merged card / header band. The
     Credentials relation-manager flatten rules are harmless on pages without an embedded RM. --}}
<style>
    .fi-sp-accordions { --surface-1: #f9fafb; --surface-2: #f3f4f6; --border: #e5e7eb; display: flex; flex-direction: column; gap: 12px; }
    .dark .fi-sp-accordions { --surface-1: #1f2937; --surface-2: #111827; --border: rgba(255,255,255,0.08); }

    .fi-sp-accordion { border: 0.5px solid var(--border); border-radius: 0.75rem; overflow: hidden; }
    .fi-sp-accordion__header { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 14px 18px; background: var(--surface-2); font-size: 14.5px; font-weight: 600; cursor: pointer; text-align: left; color: inherit; border: 0; }
    .fi-sp-accordion__header:hover { background: var(--surface-1); }
    .fi-sp-accordion__label { display: inline-flex; align-items: center; gap: 8px; }
    .fi-sp-accordion__dot { display: inline-block; width: 9px; height: 9px; border-radius: 9999px; }
    .fi-sp-accordion__chevron { flex: none; transition: transform 0.2s ease; }
    .fi-sp-accordion__chevron.is-open { transform: rotate(180deg); }
    .fi-sp-accordion__inner { background: var(--surface-1); border-top: 0.5px solid var(--border); padding: 16px 18px; }

    /* Flatten the embedded Credentials relation manager so it reads as accordion body,
       not a boxed panel: strip its section shadow/ring/border/background and radius. */
    .fi-sp-accordion__inner .fi-section,
    .fi-sp-accordion__inner .fi-section-content-ctn,
    .fi-sp-accordion__inner .fi-ta,
    .fi-sp-accordion__inner .fi-ta-ctn { box-shadow: none !important; --tw-ring-color: transparent !important; background: transparent !important; border-color: transparent !important; border-radius: 0 !important; }
    .fi-sp-accordion__inner--flush { padding: 0; }
</style>
