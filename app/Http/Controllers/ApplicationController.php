<?php

namespace App\Http\Controllers;

use App\Actions\RecordApplication;
use App\Http\Requests\StoreApplicationRequest;
use App\Models\ApplicantCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public application form (#930, ADR-0038 §8, §14.6). A visitor types the Applicant code on
 * the sign-in page, which POSTs it so it never sits in a URL or a log; the right code, while
 * published and unexpired, is kept in the session as proof for the form and the store. A wrong one is refused with the number to call, and five
 * wrong tries from one address lock the check out as the sign-in form's do. Nothing is written
 * until the store validates; the application is then spent, and the visitor sees the confirmation.
 */
class ApplicationController extends Controller
{
    /** The Membership Committee's line, as on the sign-in page. */
    public const PHONE = '(416) 586-8097';

    private const SESSION_KEY = 'applicant_code';

    private const RECEIVED = 'application_received';

    private const MAX_TRIES = 5;

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->session()->get(self::RECEIVED)) {
            return Inertia::render('apply/Received');
        }

        if (! self::codeAccepted($request)) {
            return redirect()->route('login');
        }

        return Inertia::render('apply/Form');
    }

    /** Check the typed code and open the form, or send the visitor back with the error. */
    public function code(Request $request): RedirectResponse
    {
        $typed = $request->input('code');
        $this->check($request, is_string($typed) ? $typed : '');

        return redirect()->route('apply');
    }

    public function store(StoreApplicationRequest $request, RecordApplication $action): RedirectResponse
    {
        $action->record(
            $request->safe()->except(['languages']),
            $request->validated('languages') ?? [],
            null,
        );

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->flash(self::RECEIVED, true);

        return redirect()->route('apply');
    }

    /** Whether this session entered the code and the code is still open. */
    public static function codeAccepted(Request $request): bool
    {
        $code = $request->session()->get(self::SESSION_KEY);

        return is_string($code) && ApplicantCode::current()->accepts($code);
    }

    /**
     * @throws ValidationException
     */
    private function check(Request $request, string $typed): void
    {
        $key = 'applicant-code|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_TRIES)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        if (! ApplicantCode::current()->accepts($typed)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'code' => __('apply.wrong_code', ['phone' => self::PHONE]),
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->put(self::SESSION_KEY, $typed);
    }
}
