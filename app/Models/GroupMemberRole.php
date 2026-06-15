<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\GroupMemberRoleFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A role attached to a membership (PRD #126, slice 4). Stored as its own row so
 * adding or scoping a role later is a data change, not a migration; a membership
 * may carry several at once.
 *
 * The load-bearing invariant lives here: a capability-backed role is rejected at
 * write time unless its backing Group capability flag is on, making e.g. a
 * "Scheduler on a non-scheduling Group" unrepresentable. Core roles attach to
 * any Group.
 */
class GroupMemberRole extends Model
{
    /** @use HasFactory<GroupMemberRoleFactory> */
    use HasFactory;

    /**
     * The pivot keeps its own surrogate id and timestamps, so the conventional
     * table name is set explicitly (Eloquent would otherwise pluralise it).
     *
     * @var string
     */
    protected $table = 'group_member_role';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_member_id',
        'role',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }

    /**
     * Enforce the capability-gating invariant before a role is persisted: a
     * capability-backed role may only attach to a Group whose flag is on.
     */
    protected static function booted(): void
    {
        static::saving(function (GroupMemberRole $role): void {
            $role->assertCapabilityAllowsRole();
        });
    }

    /**
     * The membership this role is attached to.
     *
     * @return BelongsTo<GroupMember, $this>
     */
    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class);
    }

    /**
     * Reject the role unless the Group it lands in has the backing capability on.
     * Core roles (no required capability) always pass.
     */
    protected function assertCapabilityAllowsRole(): void
    {
        $capability = $this->role->requiredCapability();

        if ($capability === null) {
            return;
        }

        $group = $this->groupMember->group;

        if (! $group->{$capability}) {
            throw new DomainException(
                "Role [{$this->role->value}] requires the [{$capability}] capability, which Group [{$group->id}] does not have."
            );
        }
    }
}
