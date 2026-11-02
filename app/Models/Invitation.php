<?php

namespace App\Models;

use App\Enums\InvitationState;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
