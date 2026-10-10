<?php

namespace App\Models;

use App\Http\Resources\MemberResource;
use App\Policies\SignUpPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\SignUpFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
 * Member's name is exposed to any viewer who can read the Schedule ({@see MemberResource},
 * ADR-0017 §6); the counts to every Member holding a seat on the Shift and to a schedule admin,
 * the readers of the Post-shift report (#652, ADR-0023 §5).
 *
 * The seat-holder may add an optional `comment` to their entry (#655, PRD #651): visitor
 * questions, how the shift went, or issues from the floor. Only the author and a schedule admin
 * read it ({@see SignUpPolicy::viewComment}); no report reads it. It is hidden when the model is
 * serialized (#668), so an export or API that sends a Sign-up whole never sends the comment.
 */
class SignUp extends Model
{
    /** @use HasFactory<SignUpFactory> */
    use HasFactory;

    /**
     * How far back the outstanding-shifts list reaches (#449, PRD #443, ADR-0023 §5). Legacy's
     * personal list asks for shifts from today forward **or** unconfirmed within the last 28 days;
     * we have no `Confirmed` column, so the outstanding marker is a null `visitor_count` and this
     * is the window that bounds it. A named constant, not a literal at the call site — the window
     * is a ported department policy, not an incidental number.
     */
    public const OUTSTANDING_WINDOW_DAYS = 28;

    /**
     * The session flash naming the Sign-ups post-shift saves just wrote (#668). My sign-ups keeps
     * their Shifts on the page load after the save only, so a first save shows its summary there.
     */
    public const JUST_SAVED_FLASH = 'post_shift_just_saved';

    /**
     * The session key holding the saved Sign-ups the last page load kept (#668), so a second save
     * before any other page load keeps the first seat too. Every My sign-ups load rewrites it.
     */
    public const SHOWN_SAVED_KEY = 'post_shift_shown_saved';

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
        'comment',
    ];

    /**
     * The attributes hidden when the model is serialized. The comment is private to its author
     * and a schedule admin (#655, #668); the Post-shift report adds it per reader.
     *
     * @var list<string>
     */
    protected $hidden = [
        'comment',
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
            'last_edited_at' => 'datetime',
        ];
    }

    /**
     * Record the after-the-shift numbers on this seat (#445, ADR-0023 §Model layer). Takes the
     * already-validated values — the Form Request owns every rule (required, whole, non-negative,
     * whose seat) — and saves. No transaction: it is one row, corrected by retyping. A re-file
     * overwrites; a recorded zero sticks as zero, distinct from an untouched null.
     *
     * Every save stamps `$editor` and the current time (#654, PRD #651), so the Post-shift report
     * shows who last edited the entry — the seat-holder or an officer correcting it. The stamp is
     * set here, never filled: no request value reaches it.
     *
     * @param  array<string, mixed>  $values
     */
    public function record(array $values, Member $editor): void
    {
        $this->fill($values);
        $this->last_edited_by_id = $editor->getKey();
        $this->last_edited_at = CarbonImmutable::now();
        $this->save();
    }

    /**
     * The outstanding set for a Member (#449, PRD #443, ADR-0023 §5) — their Sign-ups on Shifts
     * that have **ended**, within {@see OUTSTANDING_WINDOW_DAYS} of now, still carrying a **null
     * `visitor_count`**. That null is the only marker there is: a recorded zero is a fact, not an
     * omission, so it drops out here. The date bound sits on the Shift join (the Shift date lives
     * on `shifts`), against the current clock so time-travel in a test moves the window with it.
     * The window has a lower bound only; a Shift ended today and one ended 27 days ago both count.
     *
     * @param  Builder<SignUp>  $query
     * @return Builder<SignUp>
     */
    public function scopeOutstandingFor(Builder $query, Member $member): Builder
    {
        $now = CarbonImmutable::now();

        return $query
            ->where('member_id', $member->getKey())
            ->whereNull('visitor_count')
            ->whereHas('shift', fn (Builder $shift) => $shift
                ->where('ends_at', '<', $now)
                ->where('ends_at', '>=', $now->subDays(self::OUTSTANDING_WINDOW_DAYS)));
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

    /**
     * The Member who last saved this seat's post-shift numbers (#654); null until someone does.
     *
     * @return BelongsTo<Member, $this>
     */
    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'last_edited_by_id');
    }

    /**
     * The Objects this seat reserves (#586, ADR-0026 §3) — the handling collection the Member is
     * taking onto the floor. Reservation lives on the Sign-up, not the Shift, because several
     * volunteers may share one Shift and each reserves their own (ADR-0015). Replaced whole on an
     * edit; a retired Object keeps its link, so an old seat still names it.
     *
     * @return BelongsToMany<HandlingObject, $this>
     */
    public function objects(): BelongsToMany
    {
        // The class is prefixed but the pivot columns follow the glossary term, so the keys are
        // named explicitly rather than derived from the class name.
        return $this->belongsToMany(HandlingObject::class, 'object_sign_up', 'sign_up_id', 'object_id');
    }

    /**
     * The Tour this Sign-up records (ADR-0033 §2), or null on a kind with no Tours.
     *
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
