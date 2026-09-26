<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\OrganizerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RsvpController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DiscoveryController::class, 'home'])->name('home');
Route::get('/runs', [DiscoveryController::class, 'runs'])->name('runs.index');
Route::get('/runs/{occurrence}', [DiscoveryController::class, 'run'])->name('runs.show');
Route::get('/clubs', [DiscoveryController::class, 'clubs'])->name('clubs.index');
Route::get('/clubs/create', [ClubController::class, 'create'])->middleware(['auth', 'verified'])->name('clubs.create');
Route::get('/clubs/{club}', [DiscoveryController::class, 'club'])->name('clubs.show');
Route::view('/safety', 'static.safety')->name('safety');
Route::view('/privacy', 'static.privacy')->name('privacy');

Route::middleware('guest')->group(function () {
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::view('/forgot-password', 'auth.forgot')->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset', compact('token')))->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update')->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::view('/email/verify', 'auth.verify')->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
    Route::get('/dashboard', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile.edit');
    Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::post('/account/deletion-request', [AccountController::class, 'requestDeletion'])->name('account.deletion-request');
    Route::get('/notices', [AccountController::class, 'notices'])->name('notices.index');
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::patch('/admin/actions', [AdminController::class, 'action'])->name('admin.actions');
    Route::patch('/admin/reports/{report}', [AdminController::class, 'review'])->name('admin.reports.review');
    Route::patch('/admin/users/{user}', [AdminController::class, 'suspend'])->name('admin.users.status');
    Route::patch('/admin/clubs/{club}/hide', [AdminController::class, 'hideClub'])->name('admin.clubs.hide');
    Route::patch('/admin/occurrences/{occurrence}/unpublish', [AdminController::class, 'unpublish'])->name('admin.occurrences.unpublish');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/clubs', [ClubController::class, 'store'])->name('clubs.store');
    Route::post('/clubs/{club}/memberships', [ClubController::class, 'join'])->name('clubs.join');
    Route::post('/occurrences/{occurrence}/rsvp', [RsvpController::class, 'store'])->name('rsvps.store')->middleware('throttle:20,1');
    Route::delete('/occurrences/{occurrence}/rsvp', [RsvpController::class, 'destroy'])->name('rsvps.destroy');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store')->middleware('throttle:5,1');
    Route::get('/organizer', [OrganizerController::class, 'index'])->name('organizer.index');
    Route::get('/organizer/clubs/{club}', [ClubController::class, 'manage'])->name('organizer.clubs.show');
    Route::patch('/organizer/clubs/{club}', [ClubController::class, 'settings'])->name('organizer.clubs.update');
    Route::patch('/organizer/clubs/{club}/memberships/{membership}', [ClubController::class, 'decide'])->name('organizer.memberships.update');
    Route::get('/organizer/clubs/{club}/runs', [OrganizerController::class, 'runs'])->name('organizer.runs.index');
    Route::get('/organizer/clubs/{club}/runs/create', [OrganizerController::class, 'create'])->name('organizer.runs.create');
    Route::post('/organizer/clubs/{club}/runs', [OrganizerController::class, 'store'])->name('organizer.runs.store');
    Route::get('/organizer/occurrences/{occurrence}', [OrganizerController::class, 'edit'])->name('organizer.occurrences.edit');
    Route::patch('/organizer/occurrences/{occurrence}', [OrganizerController::class, 'update'])->name('organizer.occurrences.update');
    Route::post('/organizer/occurrences/{occurrence}/publish', [OrganizerController::class, 'publish'])->name('organizer.occurrences.publish');
    Route::post('/organizer/occurrences/{occurrence}/cancel', [OrganizerController::class, 'cancel'])->name('organizer.occurrences.cancel');
    Route::post('/organizer/occurrences/{occurrence}/updates', [OrganizerController::class, 'announce'])->name('organizer.occurrences.updates.store');
    Route::get('/organizer/occurrences/{occurrence}/roster', [OrganizerController::class, 'roster'])->name('organizer.occurrences.roster');
    Route::post('/organizer/occurrences/{occurrence}/check-in/{rsvp}', [OrganizerController::class, 'checkIn'])->name('organizer.occurrences.check-in');
});
