<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guest home = operational hub (no school PII).
 * Authenticated home = redirect to adopted /dashboard.
 */
final class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('hub');
    }
}
