<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ApplicantCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            // The Applicant code entry (#930): only while the code is published and unexpired.
            'applicationOpen' => ApplicantCode::current()->isOpen(),
            // The Membership Committee's line, from the one place the code check names it too.
            'helpPhone' => ApplicationController::PHONE,
            // A 419 handler (bootstrap/app.php) flashes sessionExpired when a
            // stale form sends a guest here; say why they have to sign in again.
            'status' => $request->session()->get('sessionExpired')
                ? __('auth.login.session_expired')
                : $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
