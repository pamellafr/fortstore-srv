<?php

use Illuminate\Support\Facades\Route;
use App\Services\FortniteApiService;

Route::get('/test-fortnite', function (FortniteApiService $service) {
    return $service->getAllCosmetics();
});

require __DIR__.'/auth.php';
