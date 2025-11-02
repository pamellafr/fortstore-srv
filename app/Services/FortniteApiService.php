<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;

class FortniteApiService
{
    public function __construct()
    {
        $response = Http::withHeaders([
            'Authorization' => config('services.fortnite.api_key'),
        ])->get('https://fortniteapi.io/v2/shop', [
            'lang' => 'en',
        ]);
    }
    public function getAllCosmetics()
    {
        $response = Http::withHeaders([
            'Authorization' => config('services.fortnite.api_key'),
        ])->get('https://fortniteapi.io/v2/items/list', [
            'lang' => 'en',
        ]);

        if ($response->successful()) {
            return $response->json()['items'];
        }

        return [];
    }
}
