<?php

namespace App\Models;

use Database\Factories\HandlingObjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A Group's Object (#584, ADR-0026 §3) — one artefact in the handling collection a Gallery
 * Interpreter takes onto the floor. The same three columns and the same maintenance shape as
 * {@see ShiftKind}: group-scoped, an `active` flag to retire without deleting, and an authored
 * `sort_order`.
 *
 * The class is prefixed `HandlingObject` because PHP reserves the bare word `Object`; the table
 * and the glossary term both stay `objects` / **Object**. A Sign-up carries zero or more Objects
 * through the `object_sign_up` pivot — reservation lives on the Sign-up, not the Shift, because
 * several volunteers may share one Shift and each reserves their own (ADR-0015). The pivot is
 * unused until the seats ticket.
 *
 * `name` is officer-authored content, stored single-column and as-authored — never translated
 * (ADR-0004).
 */
class HandlingObject extends Model
{
    /** @use HasFactory<HandlingObjectFactory> */
    use HasFactory;

    /**
     * The glossary term is the table name; only the class is prefixed.
     *
     * @var string
     */
    protected $table = 'objects';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'name',
        'active',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * The Group this Object belongs to.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The Sign-ups reserving this Object. Unused until the seats ticket (#586) puts Objects on
     * Sign-ups; the relation and its pivot exist so the schema is complete.
     *
     * @return BelongsToMany<SignUp, $this>
     */
    public function signUps(): BelongsToMany
    {
        // The class is prefixed but the pivot columns follow the glossary term, so the keys are
        // named explicitly rather than derived from the class name.
        return $this->belongsToMany(SignUp::class, 'object_sign_up', 'object_id', 'sign_up_id');
    }

    /**
     * Limit the query to active Objects — the ones still offered on new Sign-ups. A retired
     * Object still names the Sign-ups that already reserve it.
     *
     * @param  Builder<HandlingObject>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
