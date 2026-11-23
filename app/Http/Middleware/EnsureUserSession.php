<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserSession
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            $demoUser = User::where('email', 'demo@riwaq.test')->first();
            if ($demoUser) {
                Auth::login($demoUser);
            }
        }

        return $next($request);
    }
}
