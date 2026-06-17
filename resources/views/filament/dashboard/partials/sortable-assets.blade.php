{{--
    SortableJS, loaded from CDN into the dashboard panel head (same pattern as
    leaflet-assets). The spSchedulerBoard() Alpine factory below is registered once
    globally and consumed by the scheduler board view via x-data. It is the only
    place the board's drag behaviour lives.
--}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>

<script>
    // Drag controller for the Kanban board. SortableJS physically moves the card on
    // drop; we immediately revert that DOM move and let the server (via $wire) be the
    // single source of truth — Livewire's re-render then places the card in its real
    // column (or leaves it put if the move was rejected). This sidesteps the classic
    // SortableJS + Livewire morph double-management bug.
    window.spSchedulerBoard = function () {
        return {
            init() {
                this.bindColumns();

                // Re-bind after each Livewire re-render in case a column element was
                // replaced (it shouldn't be — columns carry wire:key — but cheap to guard).
                this.$nextTick(() => this.bindColumns());
            },

            bindColumns() {
                this.$root.querySelectorAll('[data-board-dropzone]').forEach((el) => {
                    if (el._sortableBound) {
                        return;
                    }

                    el._sortableBound = true;

                    Sortable.create(el, {
                        group: 'scheduler-board',
                        animation: 150,
                        draggable: '[data-intake-id]',
                        ghostClass: 'sp-board-card-ghost',
                        onEnd: (evt) => this.onDrop(evt),
                    });
                });
            },

            onDrop(evt) {
                const from = evt.from.dataset.status;
                const to = evt.to.dataset.status;
                const id = parseInt(evt.item.dataset.intakeId, 10);

                // Revert SortableJS's DOM mutation so Livewire stays authoritative.
                const reference = evt.from.children[evt.oldIndex] ?? null;
                evt.from.insertBefore(evt.item, reference);

                // TEMP DIAGNOSTIC: what the DOM produced for this drag.
                console.log('[board] drop', { id, from, to, fromEl: evt.from, toEl: evt.to });

                if (from === to || Number.isNaN(id)) {
                    return;
                }

                this.$wire.handleDrop(id, from, to);
            },
        };
    };
</script>
