<?php

use App\Http\Controllers\GhlDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', GhlDashboardController::class)->name('dashboard');
