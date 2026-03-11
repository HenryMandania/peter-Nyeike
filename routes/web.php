<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// 1. Root redirect: Direct users to their appropriate panel based on auth status
Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        return $user->hasRole('admin', 'web') 
            ? redirect('/admin') 
            : redirect('/field-operations/shifts');
    }

    return redirect('/login');
});

// 2. Centralized Login Route
// This directs all traffic to the Field Operations panel login, 
// which acts as your unified login entry point.
Route::get('/login', function () {
    return redirect()->route('filament.field-operations.auth.login');
})->name('login');