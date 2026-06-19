<?php

namespace App\Filament\Dashboard\Pages;

use App\Services\StaffPick\HelpPdfExporter;
use App\Services\StaffPick\HelpService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

/**
 * In-app help center. Renders the Markdown docs for the current user's role track
 * (scheduler / clinician / referral-source) with a manifest-driven sidebar, full-text
 * search, and a "Download as PDF" export.
 */
class Help extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?string $slug = 'help';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.dashboard.pages.help';

    #[Url]
    public ?string $topic = null;

    public string $query = '';

    public function mount(): void
    {
        $help = app(HelpService::class);
        $role = $this->role();

        if ($this->topic === null || $help->topic($role, $this->topic) === null) {
            $this->topic = $help->firstTopic($role)['slug'] ?? null;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('Help Center');
    }

    public static function getNavigationLabel(): string
    {
        return __('Help');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Support');
    }

    public function role(): string
    {
        return app(HelpService::class)->resolveRoleForUser(auth()->user());
    }

    public function roleLabel(): string
    {
        return app(HelpService::class)->roleLabel($this->role());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sections(): array
    {
        return app(HelpService::class)->roleManifest($this->role())['sections'] ?? [];
    }

    /**
     * @return array{title: string, slug: string, html: string}|null
     */
    public function currentTopic(): ?array
    {
        if ($this->topic === null) {
            return null;
        }

        return app(HelpService::class)->render($this->role(), $this->topic);
    }

    /**
     * @return array<int, array{title: string, slug: string, section: string, snippet: string}>
     */
    public function searchResults(): array
    {
        return app(HelpService::class)->search($this->role(), $this->query);
    }

    public function isSearching(): bool
    {
        return trim($this->query) !== '';
    }

    public function selectTopic(string $slug): void
    {
        $this->topic = $slug;
        $this->query = '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label(__('Download as PDF'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function () {
                    $role = $this->role();
                    $exporter = app(HelpPdfExporter::class);
                    $binary = $exporter->pdf($role);

                    return response()->streamDownload(
                        fn () => print ($binary),
                        $exporter->filename($role),
                        ['Content-Type' => 'application/pdf'],
                    );
                }),
        ];
    }
}
