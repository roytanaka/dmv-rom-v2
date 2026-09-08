<?php

use App\Models\Broadcast;
use App\Models\Member;
use Database\Seeders\OrgTreeSeeder;

/*
 * Who may read a Broadcast sent record (#488, ADR-0024 §6): Records and the sender, and
 * super-tier through the Gate::before short-circuit. No screen reads it this pass — the policy
 * is the gate a later sent-items screen leans on.
 */

beforeEach(function () {
    $this->seed(OrgTreeSeeder::class);
    $this->sender = Member::where('email', OrgTreeSeeder::SCHEDULER_EMAIL)->firstOrFail();
    $this->broadcast = Broadcast::factory()->create(['sender_id' => $this->sender->id]);
});

it('lets the sender view their own record', function () {
    expect($this->sender->can('view', $this->broadcast))->toBeTrue();
});

it('lets Records view any record', function () {
    $clerk = Member::where('email', 'clerk@dmv.test')->firstOrFail();

    expect($clerk->can('view', $this->broadcast))->toBeTrue();
});

it('lets super-tier view any record', function () {
    $president = Member::where('email', 'president@dmv.test')->firstOrFail();

    expect($president->can('view', $this->broadcast))->toBeTrue();
});

it('denies a plain Member who is neither the sender nor Records', function () {
    $trainee = Member::where('email', 'trainee@dmv.test')->firstOrFail();

    expect($trainee->can('view', $this->broadcast))->toBeFalse();
});
