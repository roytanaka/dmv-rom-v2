<?php

use App\Enums\Category;
use App\Enums\CategoryGroup;
use App\Models\Member;
use App\Personas\Persona;
use App\Personas\PersonaCatalogue;

/*
 * Sign-in is for the Current Category group only (#920, ADR-0038 §4; spec #913 stories 36, 38).
 * Every other standing fails exactly as a wrong password does. The Legacy app also let the Admin,
 * Security and Non-member groups in; the spec closes that.
 */

it('lets a Member of the Current group sign in', function (Category $category) {
    $member = Member::factory()->create(['category' => $category]);

    $this->post(route('login'), ['email' => $member->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($member);
})->with(fn () => Category::inGroup(CategoryGroup::Current));

it('fails any other standing like a wrong password', function (Category $category) {
    $member = Member::factory()->create(['category' => $category]);

    $this->post(route('login'), ['email' => $member->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
})->with(fn () => array_values(array_filter(Category::cases(), fn (Category $category) => $category->group() !== CategoryGroup::Current)));

it('fails a Past Member, whose personal data was removed', function () {
    $member = Member::factory()->create(['category' => Category::Resigned]);
    $member->forceFill(['personal_data_removed_at' => now()])->save();

    $this->post(route('login'), ['email' => $member->email, 'password' => 'password']);

    $this->assertGuest();
});

it('keeps the support operator and the super-tier personas in the Current group', function () {
    $personas = array_filter(PersonaCatalogue::all(), fn (Persona $persona) => $persona->operator || $persona->superTier);

    expect($personas)->not->toBeEmpty();

    foreach ($personas as $persona) {
        expect($persona->category->group())->toBe(CategoryGroup::Current);
    }
});

it('lets a super-tier Member and a support operator sign in', function () {
    $president = Member::factory()->create();
    $president->forceFill(['super_tier' => true])->save();
    $operator = Member::factory()->create();
    $operator->forceFill(['support_operator' => true])->save();

    foreach ([$president, $operator] as $member) {
        $this->post(route('login'), ['email' => $member->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($member);
        auth()->logout();
    }
});
