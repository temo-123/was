<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PricingController;
use App\Http\Controllers\Api\V1\VisitController;
use App\Http\Controllers\Api\V1\VisitorController;
use Illuminate\Support\Facades\Route;

/*
| All endpoints accept either a Sanctum bearer token (mobile app / external
| frontend) or the session cookie of a logged-in admin panel user.
*/

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
    });

    Route::get('prices', [PricingController::class, 'index'])->name('prices');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('me/visits', [MeController::class, 'visits'])->name('me.visits');
        Route::get('me/subscriptions', [MeController::class, 'subscriptions'])->name('me.subscriptions');

        Route::get('pricing/quote', [PricingController::class, 'quote'])->middleware('can:pricing.view')->name('pricing.quote');

        Route::get('visitors/search', [VisitorController::class, 'search'])->middleware('can:visitors.view')->name('visitors.search');
        Route::get('visitors', [VisitorController::class, 'index'])->middleware('can:visitors.view')->name('visitors.index');
        Route::post('visitors', [VisitorController::class, 'store'])->name('visitors.store'); // authorized in VisitorRequest
        Route::get('visitors/{visitor}', [VisitorController::class, 'show'])->middleware('can:visitors.view')->name('visitors.show');
        Route::put('visitors/{visitor}', [VisitorController::class, 'update'])->name('visitors.update');
        Route::delete('visitors/{visitor}', [VisitorController::class, 'destroy'])->middleware('can:visitors.delete')->name('visitors.destroy');
        Route::get('visitors/{visitor}/visits', [VisitorController::class, 'visits'])->middleware('can:visits.view')->name('visitors.visits');

        Route::get('visits', [VisitController::class, 'index'])->middleware('can:visits.view')->name('visits.index');
        Route::post('visits', [VisitController::class, 'store'])->name('visits.store'); // authorized in EntryRequest
        Route::delete('visits/{visit}', [VisitController::class, 'destroy'])->middleware('can:visits.delete')->name('visits.destroy');
    });
});
