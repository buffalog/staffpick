<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Case Calendar') }}</x-slot>

        {{-- FullCalendar's CDN script is loaded by the My Cases page view; poll until
             the global is present before initialising (Alpine may run first). --}}
        <div
            wire:ignore
            x-data="{
                calendar: null,
                init() {
                    const events = @js($this->getEvents());
                    const boot = () => {
                        if (! window.FullCalendar) {
                            setTimeout(boot, 50);
                            return;
                        }
                        this.calendar = new FullCalendar.Calendar(this.$refs.calendar, {
                            initialView: 'dayGridMonth',
                            height: 'auto',
                            headerToolbar: {
                                left: 'prev,next today',
                                center: 'title',
                                right: 'dayGridMonth,listMonth',
                            },
                            events: events,
                        });
                        this.calendar.render();
                    };
                    boot();
                },
            }"
        >
            <div x-ref="calendar" class="sp-cases-calendar text-sm"></div>
        </div>

        <style>
            /* Keep FullCalendar legible inside Filament's themed panels. */
            .sp-cases-calendar .fc .fc-toolbar-title { font-size: 1rem; }
            .sp-cases-calendar .fc .fc-day-today { background: rgba(37, 99, 235, 0.08); }
        </style>
    </x-filament::section>
</x-filament-widgets::widget>
