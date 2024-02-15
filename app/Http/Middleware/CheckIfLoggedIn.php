<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckIfLoggedIn
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the user is authenticated
        if (Auth::check()) {
            // Redirect authenticated admin to the dashboard
            $request->session()->put('requested_url', $request->url());
            if (auth()->user()->role == 'admin') {
                return redirect(route('admin.index'));
            } else if (auth()->user()->role == 'campaign_manager') {
                return redirect(route('manager.index'));
            } else {
                // Redirect authenticated agent to the dashboard
                return redirect(route('agent.index'));
            }
        }
        // Continue with the request for guests
        return $next($request);
    }
}
