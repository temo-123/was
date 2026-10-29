<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Club\CheckinController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// --- Public pages --------------------------------------------------------------------------
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/locale/{locale}', LocaleController::class)->name('locale');

// --- Club meeting check-in (QR code, no login) ---------------------------------------------
Route::get('/m/{meeting:token}', [CheckinController::class, 'show'])->name('club.checkin');
Route::post('/m/{meeting:token}', [CheckinController::class, 'store'])->middleware('throttle:20,1')->name('club.checkin.store');
Route::post('/m/{meeting:token}/forget', [CheckinController::class, 'forget'])->name('club.checkin.forget');
Route::get('/m/{meeting:token}/profile', [CheckinController::class, 'profile'])->name('club.checkin.profile');
Route::post('/m/{meeting:token}/profile', [CheckinController::class, 'updateProfile'])->middleware('throttle:20,1')->name('club.checkin.profile.update');

// --- Authentication ------------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:auth');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:auth');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:auth')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');

    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// --- Admin panel ---------------------------------------------------------------------------
Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:admin.access'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
    Route::get('/entry', Admin\EntryController::class)->middleware('can:visits.create')->name('entry');

    Route::middleware('can:visitors.view')->group(function () {
        Route::get('/visitors', [Admin\VisitorController::class, 'index'])->name('visitors.index');
        Route::get('/visitors/create', [Admin\VisitorController::class, 'create'])->middleware('can:visitors.create')->name('visitors.create');
        Route::post('/visitors', [Admin\VisitorController::class, 'store'])->name('visitors.store'); // authorized in VisitorRequest
        Route::get('/visitors/{visitor}', [Admin\VisitorController::class, 'show'])->name('visitors.show');
        Route::get('/visitors/{visitor}/edit', [Admin\VisitorController::class, 'edit'])->middleware('can:visitors.update')->name('visitors.edit');
        Route::put('/visitors/{visitor}', [Admin\VisitorController::class, 'update'])->name('visitors.update');
        Route::delete('/visitors/{visitor}', [Admin\VisitorController::class, 'destroy'])->middleware('can:visitors.delete')->name('visitors.destroy');
        Route::post('/visitors/{visitor}/link', [Admin\VisitorController::class, 'link'])->middleware('can:visitors.link')->name('visitors.link');
        Route::delete('/visitors/{visitor}/link', [Admin\VisitorController::class, 'unlink'])->middleware('can:visitors.link')->name('visitors.unlink');
    });

    Route::get('/visits', [Admin\VisitController::class, 'index'])->middleware('can:visits.view')->name('visits.index');
    Route::delete('/visits/{visit}', [Admin\VisitController::class, 'destroy'])->middleware('can:visits.delete')->name('visits.destroy');

    Route::get('/subscriptions', [Admin\SubscriptionController::class, 'index'])->middleware('can:subscriptions.view')->name('subscriptions.index');
    Route::post('/subscriptions/{subscription}/cancel', [Admin\SubscriptionController::class, 'cancel'])->middleware('can:subscriptions.cancel')->name('subscriptions.cancel');

    Route::get('/pricing', [Admin\PricingController::class, 'index'])->middleware('can:pricing.view')->name('pricing.index');
    Route::middleware('can:pricing.manage')->group(function () {
        Route::put('/pricing', [Admin\PricingController::class, 'update'])->name('pricing.update');
        Route::post('/pricing/columns', [Admin\PricingController::class, 'splitColumn'])->name('pricing.columns');
        Route::get('/pricing/settings', [Admin\PricingController::class, 'settings'])->name('pricing.settings');
        Route::post('/pricing/settings/{type}', [Admin\PricingController::class, 'saveSetting'])
            ->whereIn('type', ['age-groups', 'time-slots', 'ticket-types', 'categories'])->name('pricing.settings.save');
        Route::resource('coefficients', Admin\CoefficientController::class)->except('index', 'show');
    });

    Route::get('/users', [Admin\UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
    Route::get('/users/create', [Admin\UserController::class, 'create'])->middleware('can:users.create')->name('users.create');
    Route::post('/users', [Admin\UserController::class, 'store'])->middleware('can:users.create')->name('users.store');
    Route::get('/users/{user}/edit', [Admin\UserController::class, 'edit'])->middleware('can:users.update')->name('users.edit');
    Route::put('/users/{user}', [Admin\UserController::class, 'update'])->middleware('can:users.update')->name('users.update');
    Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->middleware('can:users.delete')->name('users.destroy');

    Route::get('/roles', [Admin\RoleController::class, 'index'])->middleware('can:roles.view')->name('roles.index');
    Route::get('/permissions', [Admin\RoleController::class, 'permissions'])->middleware('can:roles.view')->name('permissions.index');
    Route::middleware('can:roles.manage')->group(function () {
        Route::get('/roles/create', [Admin\RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [Admin\RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [Admin\RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [Admin\RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::prefix('club')->name('club.')->group(function () {
        Route::middleware('can:club.members.view')->group(function () {
            Route::get('/members', [Admin\Club\MemberController::class, 'index'])->name('members.index');
        });
        Route::middleware('can:club.members.manage')->group(function () {
            Route::get('/members/create', [Admin\Club\MemberController::class, 'create'])->name('members.create');
            Route::post('/members', [Admin\Club\MemberController::class, 'store'])->name('members.store');
            Route::get('/members/export', [Admin\Club\MemberController::class, 'export'])->name('members.export');
            Route::get('/members/template', [Admin\Club\MemberController::class, 'template'])->name('members.template');
            Route::get('/members/{member}/edit', [Admin\Club\MemberController::class, 'edit'])->name('members.edit');
            Route::put('/members/{member}', [Admin\Club\MemberController::class, 'update'])->name('members.update');
            Route::delete('/members/{member}', [Admin\Club\MemberController::class, 'destroy'])->name('members.destroy');
            Route::post('/members/{member}/approve', [Admin\Club\MemberController::class, 'approve'])->name('members.approve');
            Route::post('/members/{member}/merge', [Admin\Club\MemberController::class, 'merge'])->name('members.merge');

            Route::get('/import', [Admin\Club\MemberImportController::class, 'create'])->name('import.create');
            Route::post('/import/preview', [Admin\Club\MemberImportController::class, 'preview'])->name('import.preview');
            Route::post('/import', [Admin\Club\MemberImportController::class, 'store'])->name('import.store');
        });

        Route::get('/meetings', [Admin\Club\MeetingController::class, 'index'])->middleware('can:club.meetings.view')->name('meetings.index');
        Route::middleware('can:club.meetings.manage')->group(function () {
            Route::get('/meetings/create', [Admin\Club\MeetingController::class, 'create'])->name('meetings.create');
            Route::post('/meetings', [Admin\Club\MeetingController::class, 'store'])->name('meetings.store');
            Route::get('/meetings/{meeting}/edit', [Admin\Club\MeetingController::class, 'edit'])->name('meetings.edit');
            Route::put('/meetings/{meeting}', [Admin\Club\MeetingController::class, 'update'])->name('meetings.update');
            Route::delete('/meetings/{meeting}', [Admin\Club\MeetingController::class, 'destroy'])->name('meetings.destroy');
            Route::post('/meetings/{meeting}/close', [Admin\Club\MeetingController::class, 'close'])->name('meetings.close');
            Route::get('/meetings/{meeting}/screen', [Admin\Club\MeetingController::class, 'screen'])->name('meetings.screen');
            Route::get('/meetings/{meeting}/qr', [Admin\Club\MeetingController::class, 'qr'])->name('meetings.qr');
            Route::post('/meetings/{meeting}/attendances', [Admin\Club\MeetingController::class, 'addAttendee'])->name('meetings.attendances.store');
            Route::delete('/meetings/{meeting}/attendances/{attendance}', [Admin\Club\MeetingController::class, 'removeAttendee'])->name('meetings.attendances.destroy');
        });
        Route::middleware('can:club.meetings.view')->group(function () {
            Route::get('/meetings/{meeting}', [Admin\Club\MeetingController::class, 'show'])->name('meetings.show');
            Route::get('/meetings/{meeting}/export', [Admin\Club\MeetingController::class, 'export'])->name('meetings.export');
        });
    });

    Route::get('/activity', Admin\ActivityLogController::class)->middleware('can:activity.view')->name('activity');
});
