<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle(Request $request, Closure $next, string $role): Response
    {
        $allowedRoles = ['dev', 'admin', 'agent', 'campaign_manager'];

        // Check if the user is authenticated and has the specified role
        if (Auth::check() && in_array(auth()->user()->role, $allowedRoles) && auth()->user()->role === $role) {
            return $next($request);
        }
        //store request url in session
        // $request->session()->put('requested_url', $request->url());

        // User is not authenticated or does not have the specified role
        // Auth::logout();

        // Store the intended URL in the session before redirecting
        return redirect()->route('login')->with('error', 'You are not authorized to view that resource.');
    }
}
