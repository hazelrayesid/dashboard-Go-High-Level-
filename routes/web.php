<?php

use App\Http\Controllers\GhlDashboardController;
use App\Http\Controllers\GhlEmailWorkflowSelectionController;
use App\Http\Controllers\GhlSyncController;
use App\Http\Controllers\GhlWebhookController;
use App\Http\Controllers\GoogleCalendarController;
use Illuminate\Support\Facades\Route;

Route::get('/', GhlDashboardController::class)->name('dashboard');
Route::post('/ghl/sync', GhlSyncController::class)->name('ghl.sync');
Route::post('/ghl/email-workflows', GhlEmailWorkflowSelectionController::class)->name('ghl.email-workflows.update');
Route::post('/webhooks/ghl', GhlWebhookController::class)->name('webhooks.ghl');

Route::get('/integrations/google-calendar/connect', [GoogleCalendarController::class, 'connect'])->name('integrations.google-calendar.connect');
Route::get('/integrations/google-calendar/callback', [GoogleCalendarController::class, 'callback'])->name('integrations.google-calendar.callback');
Route::post('/integrations/google-calendar/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('integrations.google-calendar.disconnect');
