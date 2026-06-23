<?php

namespace App\Filament\Dashboard\Credentialing;

use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\ProviderCredential;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Shared form schema + persistence for manually adding a ProviderCredential, used by
 * both the provider Credentials relation manager and the Credentialing Queue page.
 *
 * "Other (freeform)" has no credential type to point at, but sp_provider_credentials
 * .document_type_id is NOT NULL — so a freeform entry is pointed at a hidden, per-tenant
 * "Other" type (is_active=false, kept out of the Policies UI and the type dropdown) and
 * the entered name is preserved in the notes field.
 */
class ManualCredential
{
    /** Sentinel option value for the freeform "Other" choice. */
    public const OTHER = 'other';

    /**
     * The credential fields shared by 2a (relation manager) and 2b (queue modal).
     *
     * @return array<int, Field>
     */
    public static function fields(int $tenantId): array
    {
        return [
            Select::make('document_type_id')
                ->label(__('Credential Type'))
                ->options(fn (): array => CredentialDocumentType::query()
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all() + [self::OTHER => __('Other (freeform)')])
                ->required()
                ->searchable()
                ->live(),
            TextInput::make('custom_type_name')
                ->label(__('Credential Name'))
                ->required()
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('document_type_id') === self::OTHER),
            TextInput::make('document_number')
                ->label(__('Document / License Number'))
                ->maxLength(255),
            DatePicker::make('issued_at')->label(__('Issue Date')),
            DatePicker::make('expires_at')->label(__('Expiry Date')),
            Textarea::make('notes')->label(__('Notes'))->rows(2),
            FileUpload::make('file_path')
                ->label(__('Document'))
                ->directory('staffpick/credentials')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']),
        ];
    }

    /**
     * Persist a manual credential for the given provider.
     *
     * @param  array<string, mixed>  $data
     */
    public static function create(array $data, int $providerId, int $tenantId): void
    {
        $isOther = ($data['document_type_id'] ?? null) === self::OTHER;

        $documentTypeId = $isOther
            ? self::otherTypeId($tenantId)
            : (int) $data['document_type_id'];

        $notes = $data['notes'] ?? null;

        // No freeform name column on the credential — preserve it in notes.
        if ($isOther && filled($data['custom_type_name'] ?? null)) {
            $notes = 'Type: '.$data['custom_type_name']."\n".($notes ?? '');
        }

        ProviderCredential::create([
            'provider_id' => $providerId,
            'document_type_id' => $documentTypeId,
            'document_number' => $data['document_number'] ?? null,
            'issued_at' => $data['issued_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'notes' => filled($notes) ? rtrim($notes) : null,
            'file_path' => $data['file_path'] ?? null,
            'status' => 'valid',
            'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED,
            'verification_source' => ProviderCredential::SOURCE_MANUAL,
        ]);
    }

    /**
     * Resolve (find or create) the per-tenant hidden "Other" credential type used to
     * satisfy the NOT NULL document_type_id for freeform entries.
     */
    private static function otherTypeId(int $tenantId): int
    {
        return CredentialDocumentType::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Other'],
            [
                'is_required' => false,
                'has_expiry' => false,
                'deactivate_on_expiry' => false,
                'expiry_warning_days' => 0,
                'verification_method' => CredentialDocumentType::METHOD_MANUAL,
                'is_active' => false,
            ],
        )->id;
    }
}
