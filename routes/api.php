<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/profile', [ApiController::class, 'profile']);
    Route::get('/books', [ApiController::class, 'books']);
    Route::get('/current-book', [ApiController::class, 'currentBook']);
    Route::get('/communities', [ApiController::class, 'communities']);
    Route::get('/challenges', [ApiController::class, 'challenges']);
    Route::get('/reading-sessions', [ApiController::class, 'readingSessions']);
});
