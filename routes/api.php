<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CosmeticController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BundleController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            
            if (!$user->credit) {
                \App\Models\Credit::create([
                    'user_id' => $user->id,
                    'amount' => 10000,
                ]);
                $user->refresh();
            }
            
            $user->load(['credit', 'cosmetics']);
            return $user;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error fetching user'], 500);
        }
    });
    
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/cosmetics/owned', [CosmeticController::class, 'owned']);
    Route::post('/cosmetics/{id}/purchase', [CosmeticController::class, 'purchase']);
    Route::post('/cosmetics/{id}/return', [CosmeticController::class, 'returnCosmetic']);
    Route::get('/users/purchase-history', [UserController::class, 'purchaseHistory']);
    Route::post('/bundles/{id}/purchase', [BundleController::class, 'purchase']);
});

Route::get('/cosmetics', [CosmeticController::class, 'index']);
Route::get('/cosmetics/new', [CosmeticController::class, 'newCosmetics']);
Route::get('/shop', [CosmeticController::class, 'shop']);
Route::get('/cosmetics/{id}', [CosmeticController::class, 'show']);
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::get('/bundles', [BundleController::class, 'index']);
Route::get('/bundles/{id}', [BundleController::class, 'show']);
