<?php

namespace App\Livewire\StaffPick;

use App\Filament\Dashboard\Pages\Help;
use App\Services\StaffPick\HelpService;
use Filament\Facades\Filament;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Contextual help slide-over. Rendered once globally on the dashboard panel; any page
 * opens it by dispatching the `open-help` Livewire event with a `path` of
 * "{role}/{slug}" (e.g. "scheduler/dispatch-board"). Renders the relevant doc topic
 * and links through to the full help center.
 */
class HelpSlideOver extends Component
{
    public bool $open = false;

    public ?string $role = null;

    public ?string $slug = null;

    #[On('open-help')]
    public function openHelp(string $path): void
    {
        $segments = explode('/', $path, 2);

        if (count($segments) === 2 && app(HelpService::class)->roleExists($segments[0])) {
            [$this->role, $this->slug] = $segments;
        } else {
            // Bare slug — resolve the role from the current user.
            $this->role = app(HelpService::class)->resolveRoleForUser(auth()->user());
            $this->slug = $path;
        }

        $this->open = true;
    }

    public function goToSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function close(): void
    {
        $this->open = false;
    }

    /**
     * @return array{title: string, slug: string, html: string}|null
     */
    public function content(): ?array
    {
        if ($this->role === null || $this->slug === null) {
            return null;
        }

        return app(HelpService::class)->render($this->role, $this->slug);
    }

    public function helpCenterUrl(): ?string
    {
        if ($this->slug === null) {
            return null;
        }

        try {
            return Help::getUrl(['topic' => $this->slug], panel: 'dashboard', tenant: Filament::getTenant());
        } catch (Throwable) {
            return null;
        }
    }

    public function render()
    {
        return view('livewire.staff-pick.help-slide-over');
    }
}
