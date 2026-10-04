<?php

// Sets one Feedback item's status and adds a comment to it, on the staging feedback database.
// Piped over SSH and run from the staging app folder:
//   ssh dmvromca@dmv-rom.ca 'cd ~/domains/staging.dmv-rom.ca/dmv-rom-v2 && /usr/local/php84/bin/php -- <id> <status> "<comment>"' < mark.php
// Status is a FeedbackStatus value: new, confirmed, fixed, wont-fix, duplicate.

use App\Enums\FeedbackStatus;
use App\Models\FeedbackItem;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $id, $status, $comment] = $argv + [null, null, null, null];

if ($id === null || $status === null || $comment === null) {
    fwrite(STDERR, "Usage: <id> <status> \"<comment>\"\n");
    exit(1);
}

$item = FeedbackItem::findOrFail($id);
$item->update(['status' => FeedbackStatus::from($status)]);
$item->comments()->create(['tester_name' => 'Support-operator', 'body' => $comment]);

echo "#{$item->id} is now {$item->status->value}: {$comment}\n";
