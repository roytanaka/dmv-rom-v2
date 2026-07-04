<?php

use App\Models\Member;
use App\Personas\PersonaCatalogue;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Dev/QA role-switcher — server half (#222, PRD #220, ADR-0009 dev half). A hand-
 * rolled become/stop over Auth::login (no package) plus the `impersonation` shared
 * prop that drives the floating toolbar. These assert external behaviour only: the
 * prop's shape/contents and the endpoints' effect on auth()->id() and the session —
 * never controller or middleware internals — so an internal refactor survives.
 *
 * The catalogued Personas the switcher becomes must exist as real accounts, so the
 * suite seeds DemoSeeder (the catalogue-driven roster), then reaches Personas by the
 * catalogue's emails and the support operator by its support_operator flag — which is
 * deliberately NOT super_tier: operating the switcher is a maintainer power, split
 * from a President's org authority.
 */

// DemoSeeder fetches best-effort DiceBear avatars for a fraction of the roster;
// fake the HTTP client so seeding never touches the network. These tests assert
// impersonation props, not photos, so an empty 200 (initials fallback) is fine.
beforeEach(function () {
    Http::fake();
    $this->seed(DemoSeeder::class);
});

function operator(): Member
{
    return Member::where('support_operator', true)->firstOrFail();
}

function persona(string $email): Member
{
    return Member::where('email', $email)->firstOrFail();
}

// --- Shared-prop visibility -------------------------------------------------

it('shares the grouped picker and no active state for an idle support operator', function () {
    $this->actingAs(operator())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('impersonation.active', null)
            ->has('impersonation.personas')
            ->where('impersonation.personas.0.key', 'operator')
            ->has('impersonation.personas.0.personas.0', fn (Assert $row) => $row
                ->hasAll(['email', 'name', 'descriptor'])));
});

it('shares no impersonation prop for an ordinary non-impersonating member', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('impersonation', null));
});

it('shares the active state to a Persona being impersonated, even with no authority', function () {
    $operator = operator();
    $departed = persona('sven.larsson@dmv.test');

    $this->actingAs($operator)
        ->post(route('impersonation.start'), ['email' => $departed->email]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('impersonation.active.as.name', 'Sven Larsson')
            ->where('impersonation.active.as.descriptor', 'Resigned · Docents')
            ->where('impersonation.active.operator', "{$operator->first_name} {$operator->last_name}")
            ->has('impersonation.personas'));
});

// --- Start ------------------------------------------------------------------

it('becomes the Persona and remembers the operator across the login', function () {
    $operator = operator();
    $chair = persona(PersonaCatalogue::CHAIR_EMAIL);

    $this->actingAs($operator)
        ->post(route('impersonation.start'), ['email' => $chair->email])
        ->assertRedirect();

    $this->assertAuthenticatedAs($chair);
    expect(session('impersonator_id'))->toBe($operator->id);
});

// --- Stop -------------------------------------------------------------------

it('returns to the operator from any tier and clears the thread', function () {
    $operator = operator();
    $chair = persona(PersonaCatalogue::CHAIR_EMAIL);

    $this->actingAs($operator)
        ->post(route('impersonation.start'), ['email' => $chair->email]);

    // Now acting as a low-privilege Persona: Stop must still work.
    $this->delete(route('impersonation.stop'))->assertRedirect();

    $this->assertAuthenticatedAs($operator);
    expect(session('impersonator_id'))->toBeNull();
});

// --- Rebase (single-level, not stacked) -------------------------------------

it('rebases to the original operator instead of stacking Personas', function () {
    $operator = operator();
    $chair = persona(PersonaCatalogue::CHAIR_EMAIL);
    $scheduler = persona(PersonaCatalogue::SCHEDULER_EMAIL);

    $this->actingAs($operator)
        ->post(route('impersonation.start'), ['email' => $chair->email]);

    // Start a second impersonation while the first is active — authorized by the
    // active session, not by operator access (the Chair is not an operator).
    $this->post(route('impersonation.start'), ['email' => $scheduler->email])
        ->assertRedirect();

    $this->assertAuthenticatedAs($scheduler);
    expect(session('impersonator_id'))->toBe($operator->id);
});

it('rejects start for a non-operator member with no active impersonation', function () {
    $this->actingAs(Member::factory()->create())
        ->post(route('impersonation.start'), ['email' => PersonaCatalogue::CHAIR_EMAIL])
        ->assertForbidden();
});

it('rejects start for a super-tier executive who is not an operator', function () {
    // The crux of the operator/authority split: a President holds super-tier org
    // authority but no operator access, so cannot run the switcher. Org authority
    // must not confer the maintainer's impersonation power.
    $president = persona('margaret.chen@dmv.test');
    expect($president->isAllDmv())->toBeTrue()
        ->and($president->isSupportOperator())->toBeFalse();

    $this->actingAs($president)
        ->post(route('impersonation.start'), ['email' => PersonaCatalogue::CHAIR_EMAIL])
        ->assertForbidden();
});

it('shares no impersonation prop for a super-tier executive who is not an operator', function () {
    $this->actingAs(persona('margaret.chen@dmv.test'))
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('impersonation', null));
});

// --- Allowlist --------------------------------------------------------------

it('refuses to become an account that is not a catalogued Persona', function () {
    $outsider = Member::factory()->create(['email' => 'not.a.persona@dmv.test']);

    $this->actingAs(operator())
        ->post(route('impersonation.start'), ['email' => $outsider->email])
        ->assertNotFound();

    $this->assertAuthenticatedAs(operator());
});

it('rejects stop when no impersonation session is active', function () {
    $this->actingAs(operator())
        ->delete(route('impersonation.stop'))
        ->assertForbidden();
});

// --- Production hard-off (two-layer environment boundary) -------------------

it('aborts the start endpoint under a production environment (controller layer)', function () {
    $this->app->detectEnvironment(fn () => 'production');

    // CSRF verification skips itself only in the testing environment; with the env
    // forced to production it would 419 before the controller runs. Drop it so the
    // request reaches the handler and we observe its own production hard-off (404).
    $this->actingAs(operator())
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('impersonation.start'), ['email' => PersonaCatalogue::CHAIR_EMAIL])
        ->assertNotFound();
});

it('shares no impersonation prop in production even for a support operator', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->actingAs(operator())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('impersonation', null));
});

it('does not register the impersonation routes in production (route layer)', function () {
    $this->app->detectEnvironment(fn () => 'production');

    // Re-register the web routes under the production environment, mirroring a real
    // production boot — the non-prod-only guard should drop the routes entirely.
    $router = $this->app['router'];
    $router->setRoutes(new RouteCollection);
    Route::middleware('web')->group(base_path('routes/web.php'));

    expect(Route::has('impersonation.start'))->toBeFalse()
        ->and(Route::has('impersonation.stop'))->toBeFalse();
});
