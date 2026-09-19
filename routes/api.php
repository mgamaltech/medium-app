<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/trending', [ArticleController::class, 'getTrending']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $request) => $request->user());

    Route::post('users/{user}/follow', [UserController::class, 'store']);

    Route::post('avatars/presigned-url', [AvatarController::class, 'getAvatarPreSignedUrl']);
    Route::post('avatars/confirm', [AvatarController::class, 'confirmAvatar']);

    Route::post('/articles', [ArticleController::class, 'store']);
    Route::patch('/articles/{article}', [ArticleController::class, 'update']);
    Route::post('/articles/{article}/comments', [CommentController::class, 'store']);

    Route::post('articles/covers/presigned-url', [ArticleController::class, 'getPresignedUrl']);

    // Booking System
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::patch('/bookings/{booking}/confirm', [BookingController::class, 'confirm']);
    Route::patch('/bookings/{booking}/reject', [BookingController::class, 'reject']);
});

Route::post('/articles/{article}/publish', [ArticleController::class, 'publish']);

Route::post('articles/covers/presigned-url', [ArticleController::class, 'getPresignedUrl']);
