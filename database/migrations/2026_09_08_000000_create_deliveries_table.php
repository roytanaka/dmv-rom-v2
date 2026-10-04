<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Delivery queue (spec #479, ADR-0024 §Delivery) — one row per queued mail, and the
     * sent record kept forever. Nothing sends inside a web request any more: a send writes
     * these rows and the every-minute Drain empties them.
     *
     * A row snapshots the recipient's address at write time (`email`) so a later address
     * change cannot redirect a mail already queued, and carries either a foreign key to what
     * it renders from (`broadcast_id`, `shift_id`) or a JSON `payload` snapshot for the kinds
     * whose source may be gone by drain time (a Notice's Sign-up, an empty-desk alert's open
     * Shifts). `broadcast_id` is a plain nullable column, not a constrained foreign key: the
     * `broadcasts` table it will point at is a later ticket (#488), and the Drain reads it
     * only when the kind is a Broadcast, which cannot yet be written.
     *
     * The uniqueness grain is (kind, shift_id, member_id): it makes "one Reminder per Shift
     * per Member" a database fact. The other kinds carry a null `shift_id`, and both SQLite
     * and MariaDB treat each SQL NULL as distinct in a unique index — so two Notices to the
     * same Member never collide, exactly as intended.
     */
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            // What this row is a copy of — the DeliveryKind enum decides the Mailable and
            // where its body renders from.
            $table->string('kind');
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            // The recipient's address as it stood when the row was written — never re-read
            // from the Member, so a queued mail cannot be redirected by a later edit.
            $table->string('email');
            // The Broadcast this row renders from (#488). Nullable and unconstrained: the
            // table does not exist yet, and only Broadcast-kind rows ever read it.
            $table->unsignedBigInteger('broadcast_id')->nullable();
            // The Shift a Reminder is keyed to; null for every other kind.
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->cascadeOnDelete();
            // The snapshot a Notice or empty-desk alert renders from — the source rows may be
            // gone by drain time, so what the mail needs is frozen here at write time.
            $table->json('payload')->nullable();
            $table->string('state')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            // When the row next becomes eligible for a send — set to write time so a fresh
            // row is due at once; the retry schedule widens it (#482).
            $table->timestamp('next_attempt_at')->nullable();
            // When the row stops being worth sending — a Reminder past its Shift's start (#482).
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            // One Reminder per (Shift, Member); nulls in the non-Reminder kinds do not collide.
            $table->unique(['kind', 'shift_id', 'member_id']);
            // The Drain's slice: pending, due, oldest first.
            $table->index(['state', 'next_attempt_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
