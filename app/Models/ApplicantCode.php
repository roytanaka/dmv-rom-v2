<?php

namespace App\Models;

use App\Support\OrgTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The Applicant code (#930, ADR-0038 §8): the one shared code the Applicant Chair hands out at an
 * intake meeting, its publish flag and its expiry. A single settings row; {@see current()}.
 *
 * @property string|null $code
 * @property bool $published
 * @property Carbon|null $expires_on
 * @property int|null $updated_by
 */
class ApplicantCode extends Model
{
    protected $table = 'applicant_code';

    protected $fillable = ['code', 'published', 'expires_on'];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'expires_on' => 'date',
        ];
    }

    /** The settings row, or an unsaved closed one before the Chair first sets it. */
    public static function current(): self
    {
        return self::query()->orderBy('id')->first() ?? new self(['published' => false]);
    }

    /** Published, with a code, and its expiry today or later (the org's today). */
    public function isOpen(): bool
    {
        return $this->published
            && filled($this->code)
            && $this->expires_on !== null
            && $this->expires_on->toDateString() >= OrgTime::today()->toDateString();
    }

    /** Whether a typed code is this one: trimmed, in either case, while the application is open. */
    public function accepts(?string $typed): bool
    {
        return $this->isOpen()
            && is_string($typed)
            && hash_equals(mb_strtolower(trim((string) $this->code)), mb_strtolower(trim($typed)));
    }
}
