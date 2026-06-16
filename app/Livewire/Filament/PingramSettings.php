<?php

namespace App\Livewire\Filament;

use App\Services\ConfigService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Component;

class PingramSettings extends Component implements HasForms
{
    private ConfigService $configService;

    use InteractsWithForms;

    public ?array $data = [];

    public function boot(ConfigService $configService): void
    {
        $this->configService = $configService;
    }

    public function render()
    {
        return view('livewire.filament.pingram-settings');
    }

    public function mount(): void
    {
        $this->form->fill([
            'api_key' => $this->configService->get('services.pingram.api_key'),
            'sms_type' => $this->configService->get('services.pingram.sms_type'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('api_key')
                            ->label(__('API Key'))
                            ->helperText(__('Your Pingram API key (starts with pingram_sk_).'))
                            ->password()
                            ->revealable()
                            ->required(),
                        TextInput::make('sms_type')
                            ->label(__('SMS Notification Type'))
                            ->helperText(__('The notification type configured in your Pingram dashboard used to send SMS.'))
                            ->required(),
                    ])->columnSpan([
                        'sm' => 6,
                        'xl' => 8,
                        '2xl' => 8,
                    ]),
                Section::make()->schema([
                    ViewField::make('how-to')
                        ->label(__('Pingram Settings'))
                        ->view('filament.admin.resources.verification-provider-resource.pages.partials.pingram-how-to'),
                ])->columnSpan([
                    'sm' => 6,
                    'xl' => 4,
                    '2xl' => 4,
                ]),
            ])->columns(12)
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->configService->set('services.pingram.api_key', $data['api_key']);
        $this->configService->set('services.pingram.sms_type', $data['sms_type']);

        Notification::make()
            ->title(__('Settings Saved'))
            ->success()
            ->send();
    }
}
