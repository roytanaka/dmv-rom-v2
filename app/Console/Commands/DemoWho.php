<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use Illuminate\Console\Command;

/**
 * Who is who in a Group's demo data (#807). Lists each membership's email, status and
 * roles from the live database, so a verify run can find a Trainee, an LOA, a Resigned
 * member or a Secretary to log in as without a tinker script.
 */
class DemoWho extends Command
{
    protected $signature = 'demo:who {group : The Group slug, e.g. docents}';

    protected $description = 'List a Group\'s memberships with email, status and roles';

    public function handle(): int
    {
        $slug = $this->argument('group');
        $group = Group::where('slug', $slug)->first();

        if ($group === null) {
            $this->error("No Group with slug \"{$slug}\".");

            return self::FAILURE;
        }

        $rows = $group->memberships()
            ->with(['member', 'roles'])
            ->get()
            ->sortBy(fn (GroupMember $membership): string => $membership->member->email)
            ->map(fn (GroupMember $membership): array => [
                $membership->member->email,
                $membership->status->value,
                $membership->roles
                    ->map(fn (GroupMemberRole $role): string => $role->role->value)
                    ->sort()
                    ->implode(', '),
            ])
            ->values()
            ->all();

        $this->table(['Email', 'Status', 'Roles'], $rows);

        return self::SUCCESS;
    }
}
