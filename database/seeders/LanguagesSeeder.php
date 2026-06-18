<?php

namespace Database\Seeders;

use App\Models\StaffPick\Language;
use Illuminate\Database\Seeder;

/**
 * Seeds the shared (global, non-tenant) sp_languages lookup used by the provider
 * onboarding wizard's "Languages spoken" field and matching's language scoring.
 *
 * The list favours the most common languages spoken in the United States, weighted
 * toward FCTS's South Florida footprint (large Spanish, Haitian Creole, and
 * Portuguese populations). Codes are ISO 639-1 where one exists, otherwise ISO 639-3
 * (e.g. cmn/yue/ase). Keyed on the unique `code`, so the seeder is idempotent.
 */
class LanguagesSeeder extends Seeder
{
    /**
     * name => code.
     *
     * @var array<string, string>
     */
    private const LANGUAGES = [
        'English' => 'en',
        'Spanish' => 'es',
        'Haitian Creole' => 'ht',
        'Portuguese' => 'pt',
        'French' => 'fr',
        'German' => 'de',
        'Italian' => 'it',
        'Russian' => 'ru',
        'Polish' => 'pl',
        'Ukrainian' => 'uk',
        'Arabic' => 'ar',
        'Hebrew' => 'he',
        'Hindi' => 'hi',
        'Urdu' => 'ur',
        'Bengali' => 'bn',
        'Punjabi' => 'pa',
        'Gujarati' => 'gu',
        'Tamil' => 'ta',
        'Telugu' => 'te',
        'Korean' => 'ko',
        'Japanese' => 'ja',
        'Mandarin Chinese' => 'cmn',
        'Cantonese' => 'yue',
        'Vietnamese' => 'vi',
        'Tagalog' => 'tl',
        'Ilocano' => 'ilo',
        'Khmer' => 'km',
        'Thai' => 'th',
        'Lao' => 'lo',
        'Indonesian' => 'id',
        'Malay' => 'ms',
        'Swahili' => 'sw',
        'Amharic' => 'am',
        'Somali' => 'so',
        'Yoruba' => 'yo',
        'Igbo' => 'ig',
        'Sign Language (ASL)' => 'ase',
        'Other' => 'other',
    ];

    public function run(): void
    {
        foreach (self::LANGUAGES as $name => $code) {
            Language::updateOrCreate(
                ['code' => $code],
                ['name' => $name],
            );
        }

        $this->command?->info('Seeded '.count(self::LANGUAGES).' languages.');
    }
}
