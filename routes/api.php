<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\AuthController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Job\SmoobuJobController;
use App\Http\Controllers\Invoice\InvoiceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('auth')->group(function() {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::prefix('users')->group(function() {
    Route::post('/register', [UserController::class, 'register']);
    Route::get('/staffs', [UserController::class, 'staffs']);

    Route::group([ 'middleware' => [ 'auth:api' ] ], function() {
        Route::middleware('role:admin')->group(function() {
            Route::delete('/{id}', [UserController::class, 'delete']);
        });
    });
});

Route::group([
    'middleware' => [
        'auth:api'
    ]
], function() {
    Route::prefix('jobs')->group(function() {
        Route::get('/assigned', [SmoobuJobController::class, 'assigned']);
        Route::get('/details/{id}', [SmoobuJobController::class, 'details']);
        Route::get('/calendar', [SmoobuJobController::class, 'calendarJobs']);
        Route::put('/assignment', [SmoobuJobController::class, 'staffAssignment']);

        Route::middleware('role:admin')->group(function() {
            Route::get('/all', [SmoobuJobController::class, 'index']);
            Route::put('/cancel', [SmoobuJobController::class, 'cancel']);
            Route::put('/update', [SmoobuJobController::class, 'update']);
            Route::delete('/delete', [SmoobuJobController::class, 'delete']);
            Route::put('/complete', [SmoobuJobController::class, 'complete']);
        });
    });

    Route::prefix('invoice')->group(function() {
        Route::middleware('role:admin')->group(function() {
            Route::get('/all', [InvoiceController::class, 'index']);
            Route::get('/download/:id', [InvoiceController::class, 'download']);
        });
    });
});