<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\UserSearchController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/tokens/read-only', [AuthController::class, 'createReadOnlyToken']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);

    Route::get('/users/search', UserSearchController::class);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::get('/projects/{project}/tasks/{task}', [ProjectController::class, 'showTask']);
    Route::patch('/projects/{project}/tasks/{task}', [ProjectController::class, 'updateTask']);
});
