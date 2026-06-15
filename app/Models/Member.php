<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Category;
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
        'super_tier',
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
     */
    public function membershipIn(Group $group): ?GroupMember
    {
        return $this->memberships()->where('group_id', $group->getKey())->first();
    }
}
