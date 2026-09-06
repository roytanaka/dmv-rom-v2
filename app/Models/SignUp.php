<?php

namespace App\Models;

use App\Http\Resources\MemberResource;
use Database\Factories\SignUpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Sign-up (#357, PRD #352, ADR-0021 §Sign-up) — one Member on one Shift. Created either
 * by the Member (self-service) or by a Scheduler placing a named regular: one entity, two
 * actors, and the row carries no record of which (no provenance column — point 2 of #334).
 *
 * The (`shift_id`, `member_id`) pair is unique: a Member holds at most one seat on a Shift.
 * Beyond its two foreign keys and timestamps the Sign-up carries the per-seat record made
 * after the shift (#445, #447, #448, PRD #443, ADR-0023 §2, §3): `visitor_count`, how many
 * visitors this Member served; on a tour-leading Group `extra_interaction_count`, how many they
 * served outside the tour they led; and on GDR alone the five origin columns
 * (`visitors_france_europe`, `visitors_quebec`, `visitors_toronto`, `visitors_rest_of_canada`,
 * `visitors_other_countries`) that must sum to `visitor_count`. **Null means nobody recorded a
 * value; zero means someone recorded zero** — the two never collapse, and the extra column is
 * stored apart from the count, folded only where a report asks for total interactions. The
 * Member's name is exposed to any viewer who can read the
 * Schedule ({@see MemberResource}, ADR-0017 §6); the counts only to the seat-holder and a
 * schedule admin.
 */
class SignUp extends Model
{
    /** @use HasFactory<SignUpFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shift_id',
        'member_id',
        'visitor_count',
        'extra_interaction_count',
        'visitors_france_europe',
        'visitors_quebec',
        'visitors_toronto',
        'visitors_rest_of_canada',
        'visitors_other_countries',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visitor_count' => 'integer',
            'extra_interaction_count' => 'integer',
            'visitors_france_europe' => 'integer',
            'visitors_quebec' => 'integer',
            'visitors_toronto' => 'integer',
            'visitors_rest_of_canada' => 'integer',
            'visitors_other_countries' => 'integer',
        ];
    }

    /**
     * Record the after-the-shift numbers on this seat (#445, ADR-0023 §Model layer). Takes the
     * already-validated values — the Form Request owns every rule (required, whole, non-negative,
     * whose seat) — and saves. No transaction: it is one row, corrected by retyping, with no
     * authorship column ([#334] stays app-wide). A re-file overwrites; a recorded zero sticks
     * as zero, distinct from an untouched null.
     *
     * @param  array<string, mixed>  $values
     */
    public function record(array $values): void
    {
        $this->fill($values)->save();
    }

    /**
     * The Shift this Sign-up takes a seat on.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * The Member holding the seat.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
