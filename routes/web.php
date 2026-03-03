<?php

use Illuminate\Support\Facades\Route;

// Redirect the root to your Field Operations panel
Route::get('/', function () {
    return redirect('/field-operations');
});