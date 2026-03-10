<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminPanelAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            return redirect('/field-operations');
        }

        if (! auth()->user()->hasRole('admin')) {
            abort(403);
        }

        return $next($request);
    }
}