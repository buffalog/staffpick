<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * {@see LogsRecordList} wired into Filament's table machinery, for any HasTable surface that
 * renders patient identifiers.
 *
 * Hooked on render() rather than getTableRecords(), for two reasons. render() runs exactly once
 * per Livewire render, which is precisely the unit this event is supposed to count, whereas
 * getTableRecords() is memoized and may be consulted several times while building one page. And
 * getTableRecords() cannot be overridden from a trait on the pages that pull InteractsWithTable
 * into the class body (MyCases, ProviderHome): two traits declaring the same method is a fatal
 * collision, and there is no parent implementation to defer to. render() is declared on the page
 * base class, so parent:: resolves the same way everywhere.
 *
 * getTableRecords() is still what supplies the ids: it is where Filament resolves what is
 * actually on screen, already accounting for the active filters, search, sort and page. Logging
 * off the underlying query instead would claim disclosures the user never saw.
 *
 * PAGINATION IS NOT DEDUPLICATED, deliberately. Page 2 is a second disclosure of a different set
 * of patients and gets its own event; the page number is in the context so the two are
 * distinguishable during review.
 */
trait LogsTableRecordList
{
    use LogsRecordList;

    public function render(): View
    {
        $records = $this->getTableRecords();

        // items() rather than getCollection(): it is on the Paginator and CursorPaginator
        // CONTRACTS, whereas getCollection() only exists on the concrete classes, and it is
        // exactly the slice rendered for this page.
        $this->logListedRecords(
            $records instanceof Collection ? $records : collect($records->items()),
            $this->listedTableContext($records),
        );

        return parent::render();
    }

    /**
     * What produced this particular list. "Searched for 'Pihl' and saw 3 results" is materially
     * more useful in an incident than "saw 3 results", and all of it is already in memory.
     *
     * Only non-empty state is recorded, so an unfiltered first page stays a small row.
     *
     * @return array<string, mixed>
     */
    protected function listedTableContext(Collection|Paginator|CursorPaginator $records): array
    {
        $context = [];

        if (! $records instanceof Collection) {
            $context['page'] = $records->currentPage();
            $context['per_page'] = $records->perPage();
        }

        if (filled($search = $this->getTableSearch())) {
            $context['search'] = $search;
        }

        if (filled($columnSearches = array_filter($this->getTableColumnSearches()))) {
            $context['column_searches'] = $columnSearches;
        }

        // tableFilters carries a nested array per filter, most of them all-null when unused.
        $filters = collect($this->tableFilters ?? [])
            ->map(fn ($state): mixed => is_array($state)
                ? array_filter($state, fn ($v): bool => $v !== null && $v !== '' && $v !== [])
                : $state)
            ->filter(fn ($state): bool => $state !== null && $state !== '' && $state !== [])
            ->all();

        if ($filters !== []) {
            $context['filters'] = $filters;
        }

        return $context;
    }
}
