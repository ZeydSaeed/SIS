<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface language from the `locale` cookie set by the title-bar «English / عربي» button.
 * Arabic stays the default; anything other than a supported locale is ignored.
 */
class HandleLocale
{
    /** @var list<string> */
    public const SUPPORTED = ['ar', 'en'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');

        if (is_string($locale) && in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
