<?php

namespace App\Console\Commands;

use App\Models\Member;
use Illuminate\Console\Command;

/**
 * Grant support-operator access (#859). The Support-operator runs it once on Beta after
 * the first import; the import keeps the marker from then on (ADR-0035 §4). The marker is
 * not fillable, so it is set directly rather than through mass assignment.
 */
class GrantOperator extends Command
{
    protected $signature = 'members:grant-operator {email : The Member\'s email address}';

    protected $description = 'Set the support_operator marker on the Member with that email';

    public function handle(): int
    {
        $email = $this->argument('email');
        $member = Member::where('email', $email)->first();

        if ($member === null) {
            $this->error("No Member with email \"{$email}\".");

            return self::FAILURE;
        }

        if ($member->isSupportOperator()) {
            $this->info("{$email} is already a support operator.");

            return self::SUCCESS;
        }

        $member->support_operator = true;
        $member->save();

        $this->info("{$email} is now a support operator.");

        return self::SUCCESS;
    }
}
