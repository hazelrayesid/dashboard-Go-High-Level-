<?php

use App\Http\Controllers\GhlDashboardController;
use App\Http\Controllers\GoogleCalendarController;
use Illuminate\Support\Facades\Route;

Route::get('/', GhlDashboardController::class)->name('dashboard');

Route::get('/integrations/google-calendar/connect', [GoogleCalendarController::class, 'connect'])->name('integrations.google-calendar.connect');
Route::get('/integrations/google-calendar/callback', [GoogleCalendarController::class, 'callback'])->name('integrations.google-calendar.callback');
Route::post('/integrations/google-calendar/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('integrations.google-calendar.disconnect');
