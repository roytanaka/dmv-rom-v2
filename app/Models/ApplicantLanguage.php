<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One language on an Applicant (#928, ADR-0038 §8), as {@see MemberLanguage} on a Member, so
 * Accept copies the rows across. A signed language is speak-only, enforced here.
 */
class ApplicantLanguage extends Model
{
    protected $fillable = [
        'language_code',
        'speaks',
        'reads',
        'writes',
    ];

    protected function casts(): array
    {
        return [
            'speaks' => 'boolean',
            'reads' => 'boolean',
            'writes' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ApplicantLanguage $language): void {
            if (in_array($language->language_code, MemberLanguage::SPEAK_ONLY_CODES, true)) {
                $language->forceFill(['speaks' => true, 'reads' => false, 'writes' => false]);
            }
        });
    }

    /**
     * @return BelongsTo<Applicant, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
