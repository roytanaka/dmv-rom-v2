<?php

namespace App\Models;

use App\Enums\InvitationState;
use App\Support\OrgTime;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * An occasion a Host asks named Members to attend (ADR-0034). It belongs to its Host,
 * not to a Group. Name and description are as-authored content (ADR-0004); the date
 * and times are wall-clock values at the museum, stored as written.
 *
 * Authority is never client-asserted: the Host is set from the signed-in Member and
 * the state starts as Draft, so neither comes from a form's validated fields.
 */
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'date',
        'start_time',
        'end_time',
        'description',
    ];

    /**
     * A new Invitation is a Draft (ADR-0034 §6).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'state' => InvitationState::Draft->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'state' => InvitationState::class,
        ];
    }

    /**
     * The start time as `HH:mm`: MariaDB hands a `time` column back with seconds.
     *
     * @return Attribute<string, never>
     */
    protected function startTime(): Attribute
    {
        return Attribute::get(fn (string $value): string => substr($value, 0, 5));
    }

    /**
     * The end time as `HH:mm`.
     *
     * @return Attribute<string, never>
     */
    protected function endTime(): Attribute
    {
        return Attribute::get(fn (string $value): string => substr($value, 0, 5));
    }

    /**
     * The Member who created this Invitation and runs it.
     *
     * @return BelongsTo<Member, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'host_id');
    }

    /**
     * The named extras a Guest may choose.
     *
     * @return HasMany<Option, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }

    /**
     * The guest list.
     *
     * @return HasMany<Guest, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    /**
     * Invitations a Guest can still reply to: Open, with a date that has not passed.
     *
     * @param  Builder<Invitation>  $query
     */
    public function scopeTakingReplies(Builder $query): void
    {
        $query->where('state', InvitationState::Open)
            ->whereDate('date', '>=', OrgTime::today()->toDateString());
    }

    /**
     * Closed is computed (ADR-0034 §6): an Open Invitation whose date has passed on the
     * museum's clock. The occasion's own day is not past, and a later date reopens it.
     */
    public function isClosed(): bool
    {
        return $this->state === InvitationState::Open && $this->date->lt(OrgTime::today());
    }

    /**
     * This Member's row on the guest list, if they have one.
     */
    public function guestFor(Member $member): ?Guest
    {
        return $this->guests()->where('member_id', $member->id)->first();
    }

    /**
     * Whether this Member may set their own Reply now: they are a Guest, and the Invitation
     * is Open and not Closed. This is not a policy ability because `Gate::before` would
     * grant it to the super-tier, who may answer only as a Guest like anyone else.
     */
    public function takesReplyFrom(Member $member): bool
    {
        return $this->state === InvitationState::Open
            && ! $this->isClosed()
            && $this->guestFor($member) !== null;
    }

    /**
     * Put these Members on the guest list. A Member already on it keeps their row and
     * their Reply, and the unique index holds this when two requests add the same Member.
     *
     * @param  Collection<int, Member>  $members
     */
    public function addGuests(Collection $members): void
    {
        $now = now();

        Guest::query()->insertOrIgnore($members->map(fn (Member $member): array => [
            'invitation_id' => $this->id,
            'member_id' => $member->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    /**
     * Save the fields and make the Options match the submitted list. An Option that
     * keeps its id keeps its row, so a rename does not break what later points at it.
     *
     * @param  array<string, mixed>  $fields
     * @param  list<array{id?: int|null, name: string}>  $options
     */
    public function saveWithOptions(array $fields, array $options): void
    {
        DB::transaction(function () use ($fields, $options): void {
            $this->fill($fields)->save();

            $kept = collect($options)->pluck('id')->filter();
            $this->options()->whereNotIn('id', $kept)->delete();

            foreach ($options as $option) {
                $id = $option['id'] ?? null;

                $id === null
                    ? $this->options()->create(['name' => $option['name']])
                    : $this->options()->whereKey($id)->update(['name' => $option['name']]);
            }
        });
    }
}
