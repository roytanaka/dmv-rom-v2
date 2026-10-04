<?php

// Prints Feedback items from the staging feedback database (ADR-0029), oldest first.
// Read-only. Piped over SSH and run from the staging app folder:
//   ssh dmvromca@dmv-rom.ca 'cd ~/domains/staging.dmv-rom.ca/dmv-rom-v2 && /usr/local/php84/bin/php -- [status ...]' < list.php
// Arguments are statuses to include. With none, it prints New and Confirmed items.

use App\Models\FeedbackItem;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$statuses = array_slice($argv, 1) ?: ['new', 'confirmed'];

$items = FeedbackItem::with(['comments', 'screenshots'])
    ->whereIn('status', $statuses)
    ->oldest()
    ->get();

echo count($items).' item(s) with status '.implode(', ', $statuses)."\n\n";

foreach ($items as $item) {
    echo "#{$item->id} [{$item->status->value}] {$item->type->value} | {$item->created_at}\n";
    echo "  tester: {$item->tester_name} | member: {$item->member_name}".($item->impersonator_name ? " (impersonated by {$item->impersonator_name})" : '')."\n";
    echo "  page: {$item->page_url} | route: {$item->route_name} | {$item->locale} | {$item->viewport_width}x{$item->viewport_height} | app: {$item->app_version}\n";
    echo '  '.str_replace("\n", "\n  ", $item->message)."\n";

    foreach ($item->screenshots as $shot) {
        echo '  screenshot: '.Storage::disk('local')->path($shot->storage_path)."\n";
    }

    foreach ($item->comments as $comment) {
        echo "  comment ({$comment->tester_name}, {$comment->created_at}): ".str_replace("\n", "\n    ", $comment->body)."\n";
    }

    echo "\n";
}
