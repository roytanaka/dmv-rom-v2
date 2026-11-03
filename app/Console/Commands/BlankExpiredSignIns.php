<?php

namespace App\Console\Commands;

use App\Models\SignIn;
use Illuminate\Console\Command;

/**
 * The Sign-in log's retention (#926, ADR-0038 §12): past one year a row loses its IP address and
 * user agent and stays, for statistics and "last sign-in". Safe to run twice: a blanked row no
 * longer matches. Scheduled nightly in `routes/console.php`.
 */
class BlankExpiredSignIns extends Command
{
    protected $signature = 'sign-ins:blank-expired';

    protected $description = 'Blank the IP address and user agent on Sign-in log rows past one year';

    public function handle(): int
    {
        SignIn::query()
            ->where('signed_in_at', '<', now()->subYear())
            ->where(fn ($query) => $query->whereNotNull('ip')->orWhereNotNull('user_agent'))
            ->update(['ip' => null, 'user_agent' => null]);

        return self::SUCCESS;
    }
}
