<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Renewal settings (#937, ADR-0038 §9): the one row that drives a year's Renewal. The show flag
 * opens it, the renewal year names the year it serves, and each of Active and Sustaining has a
 * send date and two reminder texts; Sustaining also has an attachment. External addresses (empty
 * by default) get a copy of the renewal and applicant Notices (ADR-0038 §14.5). A single settings
 * row; read it through {@see current()}.
 *
 * @property bool $show_renewal
 * @property int|null $renewal_year
 * @property Carbon|null $active_send_date
 * @property Carbon|null $sustaining_send_date
 * @property string|null $active_message_1
 * @property string|null $active_message_2
 * @property string|null $sustaining_message_1
 * @property string|null $sustaining_message_2
 * @property string|null $sustaining_attachment_path
 * @property string|null $sustaining_attachment_filename
 * @property string|null $sustaining_attachment_mime_type
 * @property list<string> $external_addresses
 * @property int|null $updated_by
 * @property-read Member|null $updatedBy
 */
class RenewalSetting extends Model
{
    protected $fillable = [
        'show_renewal', 'renewal_year', 'active_send_date', 'sustaining_send_date',
        'active_message_1', 'active_message_2', 'sustaining_message_1', 'sustaining_message_2',
        'external_addresses',
    ];

    protected $attributes = [
        'show_renewal' => false,
        'external_addresses' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'show_renewal' => 'boolean',
            'renewal_year' => 'integer',
            'active_send_date' => 'date',
            'sustaining_send_date' => 'date',
            'external_addresses' => 'array',
        ];
    }

    /** The settings row, or an unsaved closed one before member administration first saves it. */
    public static function current(): self
    {
        return self::query()->orderBy('id')->first() ?? new self;
    }

    /** @return BelongsTo<Member, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'updated_by');
    }
}
