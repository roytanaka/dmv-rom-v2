<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Category;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Member extends Authenticatable
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'category',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'category' => Category::class,
            'super_tier' => 'boolean',
        ];
    }

    /**
     * Whether this Member holds the single org-wide "all-DMV" access grant.
     *
     * This is the only authority that reaches across the whole DMV; every other
     * grant is scoped to a Group via the membership pivot (later spine slices).
     */
    public function isAllDmv(): bool
    {
        return $this->super_tier;
    }

    /**
     * Every membership this Member holds, one per Group they belong to.
     *
     * @return HasMany<GroupMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Fetch this Member's membership in a given Group, or null if they aren't a
     * member of it. Standing is per-Group, so this is the entry point every
     * Group-scoped authorization decision reads.
     *
     * Resolves against the `memberships` relation collection, which loads once
     * and then resolves in memory — so a gate/policy that asks repeated
     * authorization questions about one Member (or eager-loads
     * `memberships.roles`) never re-queries per call.
     */
    public function membershipIn(Group $group): ?GroupMember
    {
        return $this->memberships->firstWhere('group_id', $group->getKey());
    }

    /**
     * Whether this Member literally holds the given role in the given Group —
     * the "is an X" check for display. Authorization decisions call
     * {@see canActAs()} instead, which also folds in Chair-implication. False
     * when the Member isn't in the Group.
     */
    public function holdsRole(Role $role, Group $group): bool
    {
        return $this->membershipIn($group)
            ?->roles->contains('role', $role) ?? false;
    }

    /**
     * Whether this Member can act with the authority of the given role in the
     * given Group — the single resolver every gate and policy calls. Unlike
     * {@see holdsRole()}, this folds in Chair-implication: a Chair implies every
     * officer role within its *own* Group except `Treasurer`, whose finance
     * authority requires the explicit role (separation of duties). Authority
     * never leaks across Groups — a role held in Group A grants nothing in
     * Group B.
     *
     * Matches group-wide role rows only. There is no subdivision column yet;
     * when ADR-0010's subdivision-scoped roles land, this keeps matching
     * group-wide rows and the narrowing lives in the consuming capability's own
     * policy — the seam is preserved here, not built.
     */
    public function canActAs(Role $role, Group $group): bool
    {
        $membership = $this->membershipIn($group);

        if ($membership === null) {
            return false;
        }

        if ($membership->roles->contains('role', $role)) {
            return true;
        }

        return $role !== Role::Treasurer
            && $membership->roles->contains('role', Role::Chair);
    }

    /**
     * Whether this Member holds member-administration authority — i.e. is a member
     * of the Group that stewards `member_admin` (the Records Group). Authority is
     * explicit and per-Group (ADR-0011): reach over member administration comes
     * from membership in the stewarding Group, never a standalone flag. False when
     * no Group stewards the function.
     */
    public function hasMemberAdminAuthority(): bool
    {
        $records = Group::stewardOf(StewardshipFunction::MemberAdmin);

        return $records !== null && $this->membershipIn($records) !== null;
    }
}
