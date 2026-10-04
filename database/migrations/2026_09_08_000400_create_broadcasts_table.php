<?php

use App\Models\Delivery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Broadcast sent record (spec #479, ADR-0024 §6) — one row per composer send.
     * A send re-resolves its Audience on the server, writes this row, then one Broadcast
     * {@see Delivery} per recipient and one sender-copy row, all pointing back
     * here by `broadcast_id`. Nothing sends in the request; the Drain empties the queue.
     *
     * The record keeps what the send was: who sent it, the Group it was scoped to (null
     * for an org-wide send or a Direct message), the Audience's key and stored label, the
     * edited flag, the resolved recipient count, the subject, the sanitized HTML body, the
     * attachment manifest, and the queued time. Sent and failed counts are NOT stored —
     * they are derived from the Delivery rows, which are the recipient list and are kept
     * forever (ADR-0024 §6). A policy grants view to Records and the sender; no screen
     * reads it this pass.
     *
     * `attachments` holds the manifest the composer showed (name and size) plus the private
     * -disk path the file was stored under, so the Drain can attach it at send time and
     * delete it once the sender's copy has gone out (ADR-0024 §4).
     */
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            // Broadcast or Direct message — the BroadcastKind enum; only Broadcast is
            // written this ticket.
            $table->string('kind');
            // The officer (or Member) who wrote it — the mail's Reply-To, and one of the
            // two the view policy grants.
            $table->foreignId('sender_id')->constrained('members')->cascadeOnDelete();
            // The Group the send was scoped to; null for an org-wide Broadcast or a Direct
            // message, which belong to no one Group.
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            // The Audience the browser named and the label the record keeps — resolved on
            // the server, never a posted recipient list (ADR-0024 §5).
            $table->string('audience_key');
            $table->string('audience_label');
            // Whether the picker removed or added anyone; the label already carries the
            // "N removed" suffix when it did.
            $table->boolean('edited')->default(false);
            // The count as resolved, after the no-email skip — the number of Broadcast
            // Delivery rows written.
            $table->unsignedInteger('recipient_count');
            $table->string('subject');
            // The body as sent, sanitized against the allowlist at write time — content in
            // one language, never re-rendered per locale (ADR-0024 §3).
            $table->text('body');
            // The attachment manifest: name, size, and the private-disk path. Empty array
            // when there were none.
            $table->json('attachments');
            $table->timestamp('queued_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
