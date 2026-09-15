<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single app entry: authenticated users → /dashboard; guests → login.
 */
final class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        return redirect()->guest(route('login'));
    }
}
