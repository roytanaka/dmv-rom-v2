<?php

namespace App\Models;

use App\Enums\ApplicantStatus;
use App\Enums\DepartureReason;
use App\Policies\ApplicantPolicy;
use App\Support\OrgTime;
use Database\Factories\ApplicantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An Applicant (#928, ADR-0038 §8): a person who applied to volunteer, tracked through intake by
 * the Group that holds the `applicants` Stewardship. Kept apart from Members; Accept links the
 * Member it became through `member_id` and keeps this row. Gated by {@see ApplicantPolicy}.
 *
 * `notes` is a log, written only through {@see prependNote()}; `status_date` moves only with a
 * status change.
 */
class Applicant extends Model
{
    /** @use HasFactory<ApplicantFactory> */
    use HasFactory;

    /**
     * The fields an office form edits directly. Status, mentor and the rest of intake's state are
     * fillable too: every write goes through a Form Request whitelist.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'business_phone',
        'address_street',
        'address_city',
        'address_province',
        'address_postal_code',
        'address_country',
        'membership_number',
        'membership_expiry',
        'membership_lifetime',
        'emergency_contact',
        'emergency_contact_number',
        'status',
        'departure_reason',
        'placement_group_id',
        'mentor_id',
        'applied_on',
        'entry_year',
        'volunteer_experience',
        'past_experience',
        'education',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicantStatus::class,
            'status_date' => 'date',
            'departure_reason' => DepartureReason::class,
            'membership_expiry' => 'date',
            'membership_lifetime' => 'boolean',
            'entered_by_applicant' => 'boolean',
            'applied_on' => 'date',
        ];
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Prepend a dated, signed line to the Notes log, as {@see Member::prependNote()} does. The
     * Legacy app's line carried no author; this one does.
     */
    public function prependNote(string $text, Member $author): void
    {
        $line = OrgTime::now()->toDateString().' '.$author->fullName().': '.$text;
        $earlier = $this->getAttributes()['notes'] ?? null;

        $this->notes = filled($earlier) ? $line."\n".$earlier : $line;
    }

    /**
     * Save an edit of the record as the actor's: the one way "Last updated by" is written.
     */
    public function markUpdatedBy(Member $actor): void
    {
        $this->updated_by = $actor->id;
        $this->touch();
    }

    /**
     * The holder member who mentors this case. Null while unassigned, as a self-entered
     * application starts.
     *
     * @return BelongsTo<Member, $this>
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'mentor_id');
    }

    /**
     * The Group the Applicant is placed in.
     *
     * @return BelongsTo<Group, $this>
     */
    public function placementGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'placement_group_id');
    }

    /**
     * The Member this Applicant became, once accepted.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Who entered the application; null when the applicant entered it ({@see $entered_by_applicant}).
     *
     * @return BelongsTo<Member, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'updated_by');
    }

    /**
     * @return HasMany<ApplicantLanguage, $this>
     */
    public function languages(): HasMany
    {
        return $this->hasMany(ApplicantLanguage::class);
    }
}
