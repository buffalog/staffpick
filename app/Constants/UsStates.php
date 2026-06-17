<?php

namespace App\Constants;

/**
 * Canonical list of US state (and territory) postal abbreviations, used to constrain
 * the provider address `state` field to a clean 2-letter code. This is load-bearing:
 * the license-verification RapidAPI call sends `provider.state` verbatim, so it must
 * be a valid postal abbreviation.
 */
final class UsStates
{
    /**
     * Postal abbreviation => full name.
     *
     * @var array<string, string>
     */
    public const ABBREVIATIONS = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
        'PR' => 'Puerto Rico',
        'VI' => 'U.S. Virgin Islands',
        'GU' => 'Guam',
    ];

    /**
     * Filament Select options, labelled "Florida (FL)" for searchability by either the
     * full name or the abbreviation.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::ABBREVIATIONS as $abbreviation => $name) {
            $options[$abbreviation] = "{$name} ({$abbreviation})";
        }

        return $options;
    }
}
