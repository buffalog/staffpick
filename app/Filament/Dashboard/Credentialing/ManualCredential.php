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
 * Type-first flow (spec section 3): the Credential Type dropdown is the only field shown
 * until a type is chosen; the rest (document number, dates, notes, file) unlock after
 * selection, and the expiry date shows only for types that actually expire.
 *
 * "Other" is the last option and promotes on submit (section 4): the typed name becomes
 * a real, immediately-available credential type — no review queue — de-duplicated against
 * existing names by a trim + case-insensitive collision check.
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
                    ->visibleToCurrentUser()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all() + [self::OTHER => __('Other')])
                ->required()
                ->searchable()
                ->live(),

            // "Other": name the document exactly as titled on its own header — not a
            // paraphrase — so the promoted type is a clean, reusable label.
            TextInput::make('custom_type_name')
                ->label(__('Credential Name'))
                ->helperText(__('Name this exactly as it appears at the top of the document itself.'))
                ->required()
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('document_type_id') === self::OTHER),

            // Everything below unlocks only once a type is chosen.
            TextInput::make('document_number')
                ->label(__('Document / License Number'))
                ->maxLength(255)
                ->visible(fn (Get $get): bool => self::typeChosen($get)),

            DatePicker::make('issued_at')
                ->label(__('Issue Date'))
                ->visible(fn (Get $get): bool => self::typeChosen($get)),

            DatePicker::make('expires_at')
                ->label(__('Expiry Date'))
                ->visible(fn (Get $get): bool => self::showExpiry($get, $tenantId)),

            Textarea::make('notes')
                ->label(__('Notes'))
                ->rows(2)
                ->visible(fn (Get $get): bool => self::typeChosen($get)),

            FileUpload::make('file_path')
                ->label(__('Document'))
                ->directory('staffpick/credentials')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->visible(fn (Get $get): bool => self::typeChosen($get)),
        ];
    }

    /**
     * Persist a manual credential for the given provider. An "Other" selection promotes
     * the typed name to a real credential type first (see promoteOtherType).
     *
     * @param  array<string, mixed>  $data
     */
    public static function create(array $data, int $providerId, int $tenantId): void
    {
        $selected = $data['document_type_id'] ?? null;

        $documentTypeId = ($selected === self::OTHER)
            ? self::promoteOtherType((string) ($data['custom_type_name'] ?? ''), $tenantId, filled($data['expires_at'] ?? null))
            : (int) $selected;

        $notes = $data['notes'] ?? null;

        ProviderCredential::firstOrCreate(
            [
                'provider_id' => $providerId,
                'document_type_id' => $documentTypeId,
            ],
            [
                'document_number' => $data['document_number'] ?? null,
                'issued_at' => $data['issued_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'notes' => filled($notes) ? rtrim((string) $notes) : null,
                'file_path' => $data['file_path'] ?? null,
                'status' => 'valid',
                'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED,
                'verification_source' => ProviderCredential::SOURCE_MANUAL,
            ]
        );
    }

    /**
     * Whether a credential type has been selected yet (gates the rest of the form).
     */
    private static function typeChosen(Get $get): bool
    {
        return filled($get('document_type_id'));
    }

    /**
     * Show the expiry date only for types that expire. "Other" shows it (the type is new,
     * so we don't yet know — allow entry; a supplied date sets the new type's has_expiry).
     */
    private static function showExpiry(Get $get, int $tenantId): bool
    {
        $value = $get('document_type_id');

        if (blank($value)) {
            return false;
        }

        if ($value === self::OTHER) {
            return true;
        }

        return (bool) CredentialDocumentType::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($value)
            ->value('has_expiry');
    }

    /**
     * Other-promotion (spec section 4): the typed name becomes a real credential type
     * immediately, available in the dropdown for the very next entry. Before creating,
     * normalize (trim + case-insensitive) against existing type names — including ones
     * created moments earlier — so trivial formatting variance doesn't spawn a near-dup.
     * This is a collision check, not a review gate: fully automatic, no human in the loop.
     *
     * New types default visible_to_scheduler=false (HR-only until reclassified) — safer
     * than exposing something HR-only to schedulers by default.
     */
    private static function promoteOtherType(string $name, int $tenantId, bool $hasExpiry): int
    {
        $trimmed = trim($name);
        $normalized = mb_strtolower($trimmed);

        $existing = CredentialDocumentType::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->first(fn (CredentialDocumentType $type): bool => mb_strtolower(trim((string) $type->name)) === $normalized);

        if ($existing !== null) {
            return (int) $existing->getKey();
        }

        return (int) CredentialDocumentType::create([
            'tenant_id' => $tenantId,
            'name' => $trimmed,
            'is_required' => false,
            'has_expiry' => $hasExpiry,
            'expiry_warning_days' => $hasExpiry ? 30 : 0,
            'deactivate_on_expiry' => false,
            'is_active' => true,
            'visible_to_scheduler' => false,
            'verification_method' => CredentialDocumentType::METHOD_MANUAL,
        ])->getKey();
    }
}
