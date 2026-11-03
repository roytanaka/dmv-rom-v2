<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One language on a Member record (#918, ADR-0038 §3): the code of a known language, or the name
 * of any other, with what the Member can do in it. A signed language is speak-only, enforced here
 * so no writer can store it otherwise.
 */
class MemberLanguage extends Model
{
    /**
     * The codes of the languages the record names; anything else is a free "other" language.
     *
     * @var list<string>
     */
    public const KNOWN_CODES = ['en', 'fr', 'asl', 'lsq'];

    /** @var list<string> */
    public const SPEAK_ONLY_CODES = ['asl', 'lsq'];

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
        static::saving(function (MemberLanguage $language): void {
            if (in_array($language->language_code, self::SPEAK_ONLY_CODES, true)) {
                $language->forceFill(['speaks' => true, 'reads' => false, 'writes' => false]);
            }
        });
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
