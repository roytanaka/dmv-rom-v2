<?php

namespace App\Http\Requests\Concerns;

use App\Models\MemberLanguage;

/**
 * The fields of an application (#928, ADR-0038 §8): what the public form (#930), Add applicant and
 * the Applicant record all take. First and last name, email and a primary phone of at least ten
 * characters are required, as the Legacy app's save required them. Languages arrive as a list of
 * rows in the `member_languages` shape; a known language typed as "other" folds into its code.
 */
trait ValidatesApplication
{
    private const APPLICATION_LINE = ['nullable', 'string', 'max:255'];

    /**
     * Call from prepareForValidation().
     */
    protected function normalizeLanguages(): void
    {
        $languages = $this->input('languages');

        if (! is_array($languages)) {
            return;
        }

        $this->merge(['languages' => array_map(function ($row) {
            if (! is_array($row) || ! is_string($row['language_code'] ?? null)) {
                return $row;
            }

            $code = trim($row['language_code']);
            $lower = mb_strtolower($code);

            return [...$row, 'language_code' => in_array($lower, MemberLanguage::KNOWN_CODES, true) ? $lower : $code];
        }, $languages)]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function applicationRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:255'],
            'alternate_phone' => self::APPLICATION_LINE,
            'business_phone' => self::APPLICATION_LINE,
            'address_street' => self::APPLICATION_LINE,
            'address_city' => self::APPLICATION_LINE,
            'address_province' => self::APPLICATION_LINE,
            'address_postal_code' => self::APPLICATION_LINE,
            'address_country' => self::APPLICATION_LINE,
            'membership_number' => self::APPLICATION_LINE,
            'membership_expiry' => ['nullable', 'date'],
            'membership_lifetime' => ['nullable', 'boolean'],
            'emergency_contact' => self::APPLICATION_LINE,
            'emergency_contact_number' => self::APPLICATION_LINE,
            ...$this->languageRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function languageRules(): array
    {
        return [
            'languages' => ['nullable', 'array', 'max:20'],
            'languages.*.language_code' => ['required', 'string', 'max:64', 'distinct:ignore_case'],
            'languages.*.speaks' => ['boolean'],
            'languages.*.reads' => ['boolean'],
            'languages.*.writes' => ['boolean'],
        ];
    }
}
