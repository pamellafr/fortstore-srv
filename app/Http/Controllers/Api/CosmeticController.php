<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cosmetic;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class CosmeticController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Cosmetic::query()->with('images');

            if ($request->has('search') && ($search = trim($request->input('search')))) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->has('type') && ($type = trim($request->input('type')))) {
                $typeMap = [
                    'outfit' => ['outfit', 'character'],
                    'backpack' => ['backpack', 'back bling', 'backbling'],
                    'pickaxe' => ['harvesting tool', 'harvestingtool', 'pickaxe', 'harvest'],
                    'glider' => ['glider'],
                    'emote' => ['emote', 'emoticon'],
                    'wrap' => ['wrap'],
                    'music' => ['music'],
                ];
                
                $typeValues = $typeMap[strtolower($type)] ?? [strtolower($type)];
                $typeLower = strtolower($type);
                
                $query->where(function ($q) use ($typeValues, $typeLower) {
                    $q->whereRaw('LOWER(type_name) LIKE ?', ['%' . $typeLower . '%']);
                    
                    foreach ($typeValues as $typeValue) {
                        $q->orWhereRaw('LOWER(type_name) LIKE ?', ['%' . strtolower($typeValue) . '%']);
                    }
                });
            }

            if ($request->has('rarity') && ($rarity = trim($request->input('rarity')))) {
                $query->whereRaw('LOWER(rarity_name) = ?', [strtolower($rarity)]);
            }

            if ($request->has('dateFrom') && ($dateFrom = $request->input('dateFrom'))) {
                $query->whereDate('added_date', '>=', $dateFrom);
            }

            if ($request->has('dateTo') && ($dateTo = $request->input('dateTo'))) {
                $query->whereDate('added_date', '<=', $dateTo);
            }

            if ($request->boolean('onlyNew')) {
                $query->where(function ($q) {
                    $q->where(function ($dateQ) {
                        $dateQ->whereNotNull('added_date')
                            ->whereDate('added_date', '>=', Carbon::now()->subDays(30));
                    })->orWhere(function ($createdQ) {
                        $createdQ->whereNull('added_date')
                            ->whereDate('created_at', '>=', Carbon::now()->subDays(30));
                    })->orWhere(function ($updatedQ) {
                        $updatedQ->whereDate('updated_at', '>=', Carbon::now()->subDays(30));
                    });
                });
            }

            if ($request->boolean('onlyOnSale')) {
                $query->where(function ($q) {
                    $q->where(function ($interestQ) {
                        $interestQ->where('interest', '>=', 0.6)
                            ->where('interest', '<', 0.75);
                    })->orWhereDate('last_appearance', '>=', Carbon::now()->subDays(7));
                });
            }

            if ($request->boolean('onlyPromoted')) {
                $query->where('interest', '>=', 0.75);
            }

            $perPage = $request->input('per_page', 50);
            $perPage = is_numeric($perPage) ? (int) $perPage : 50;
            $perPage = min(max($perPage, 1), 1000);

            $cosmetics = $query->orderByDesc('added_date')
                ->orderByDesc('created_at')
                ->paginate($perPage)
                ->withQueryString();

            $user = null;
            $userCosmeticIds = [];
            
            try {
                $bearerToken = $request->bearerToken();
                if ($bearerToken) {
                    $token = PersonalAccessToken::findToken($bearerToken);
                    if ($token && $token->tokenable) {
                        $user = $token->tokenable;
                        $userCosmetics = $user->cosmetics()->get();
                        $userCosmeticIds = $userCosmetics->pluck('id')->toArray();
                    }
                }
            } catch (\Exception $e) {
            }

            $cosmetics->getCollection()->transform(function ($cosmetic) use ($userCosmeticIds, $user) {
                if ($cosmetic->images) {
                    $cosmetic->images = $cosmetic->images->filter(function ($image) {
                        if (!$image->url) return false;
                        $url = trim((string) $image->url);
                        return $url !== '' && 
                               $url !== 'null' && 
                               $url !== 'undefined' && 
                               !str_contains(strtolower($url), 'undefined') &&
                               strlen($url) > 10;
                    })->values();
                }
                
                if ($user && !empty($userCosmeticIds)) {
                    $cosmetic->is_owned = (bool) in_array($cosmetic->id, $userCosmeticIds);
                } else {
                    $cosmetic->is_owned = false;
                }
                
                $cosmetic->is_new = $cosmetic->is_new;
                $cosmetic->is_on_sale = $cosmetic->is_on_sale;
                $cosmetic->is_promoted = $cosmetic->is_promoted;
                
                return $cosmetic;
            });

            return response()->json($cosmetics);
        } catch (\Exception $e) {
            return response()->json(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0], 200);
        }
    }

    public function newCosmetics(Request $request)
    {
        try {
            $days = $request->input('days', 14);
            $days = is_numeric($days) ? (int) $days : 14;
            $limit = $request->input('limit', 24);
            $limit = is_numeric($limit) ? (int) $limit : 24;

            $cosmetics = Cosmetic::with('images')
                ->whereDate('added_date', '>=', Carbon::now()->subDays($days))
                ->orderByDesc('added_date')
                ->take($limit)
                ->get();

            if ($cosmetics->isEmpty()) {
                $fallbackLimit = $request->input('fallback_limit', 24);
                $fallbackLimit = is_numeric($fallbackLimit) ? (int) $fallbackLimit : 24;
                $cosmetics = Cosmetic::with('images')
                    ->orderByDesc('created_at')
                    ->take($fallbackLimit)
                    ->get();
            }

            $user = null;
            $userCosmeticIds = [];
            
            try {
                $bearerToken = $request->bearerToken();
                if ($bearerToken) {
                    $token = PersonalAccessToken::findToken($bearerToken);
                    if ($token) {
                        $user = $token->tokenable;
                        if ($user) {
                            $userCosmetics = $user->cosmetics()->get();
                            $userCosmeticIds = $userCosmetics->pluck('id')->toArray();
                        }
                    }
                }
            } catch (\Exception $e) {
            }

            $cosmetics->transform(function ($cosmetic) use ($userCosmeticIds, $user) {
                if ($cosmetic->images) {
                    $cosmetic->images = $cosmetic->images->filter(function ($image) {
                        if (!$image->url) return false;
                        $url = trim((string) $image->url);
                        return $url !== '' && 
                               $url !== 'null' && 
                               $url !== 'undefined' && 
                               !str_contains(strtolower($url), 'undefined') &&
                               strlen($url) > 10;
                    })->values();
                }
                
                if ($user && !empty($userCosmeticIds)) {
                    $cosmetic->is_owned = (bool) in_array($cosmetic->id, $userCosmeticIds);
                } else {
                    $cosmetic->is_owned = false;
                }
                
                $cosmetic->is_new = $cosmetic->is_new;
                $cosmetic->is_on_sale = $cosmetic->is_on_sale;
                $cosmetic->is_promoted = $cosmetic->is_promoted;
                
                return $cosmetic;
            });

            return response()->json($cosmetics);
        } catch (\Exception $e) {
            return response()->json([], 200);
        }
    }

    public function shop(Request $request)
    {
        try {
            $limit = $request->input('limit', 24);
            $limit = is_numeric($limit) ? (int) $limit : 24;

            $cosmetics = Cosmetic::with('images')
                ->whereNotNull('price')
                ->orderByDesc('interest')
                ->take($limit)
                ->get();

            if ($cosmetics->isEmpty()) {
                $cosmetics = Cosmetic::with('images')
                    ->orderByDesc('updated_at')
                    ->take($limit)
                    ->get();
            }

            $user = null;
            $userCosmeticIds = [];
            
            try {
                $bearerToken = $request->bearerToken();
                if ($bearerToken) {
                    $token = PersonalAccessToken::findToken($bearerToken);
                    if ($token) {
                        $user = $token->tokenable;
                        if ($user) {
                            $userCosmetics = $user->cosmetics()->get();
                            $userCosmeticIds = $userCosmetics->pluck('id')->toArray();
                        }
                    }
                }
            } catch (\Exception $e) {
            }

            $cosmetics->transform(function ($cosmetic) use ($userCosmeticIds, $user) {
                if ($cosmetic->images) {
                    $cosmetic->images = $cosmetic->images->filter(function ($image) {
                        if (!$image->url) return false;
                        $url = trim((string) $image->url);
                        return $url !== '' && 
                               $url !== 'null' && 
                               $url !== 'undefined' && 
                               !str_contains(strtolower($url), 'undefined') &&
                               strlen($url) > 10;
                    })->values();
                }
                
                if ($user && !empty($userCosmeticIds)) {
                    $cosmetic->is_owned = (bool) in_array($cosmetic->id, $userCosmeticIds);
                } else {
                    $cosmetic->is_owned = false;
                }
                
                $cosmetic->is_new = $cosmetic->is_new;
                $cosmetic->is_on_sale = $cosmetic->is_on_sale;
                $cosmetic->is_promoted = $cosmetic->is_promoted;
                
                return $cosmetic;
            });

            return response()->json($cosmetics);
        } catch (\Exception $e) {
            return response()->json([], 200);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $cosmetic = Cosmetic::with('images')->findOrFail($id);
            
            $cosmetic->images = $cosmetic->images->filter(function ($image) {
                if (!$image->url) return false;
                $url = trim((string) $image->url);
                return $url !== '' && 
                       $url !== 'null' && 
                       $url !== 'undefined' && 
                       !str_contains(strtolower($url), 'undefined') &&
                       !str_starts_with(strtolower($url), 'data:image/svg+xml;base64,phn2z');
            })->values();
            
            $user = null;
            try {
                $bearerToken = $request->bearerToken();
                if ($bearerToken) {
                    $token = PersonalAccessToken::findToken($bearerToken);
                    if ($token) {
                        $user = $token->tokenable;
                    }
                }
            } catch (\Exception $e) {
            }
            
            if ($user) {
                $isOwned = $user->cosmetics()->where('user_cosmetics.cosmetic_id', $id)->exists();
                $cosmetic->is_owned = $isOwned;
            } else {
                $cosmetic->is_owned = false;
            }
            
            $cosmetic->is_new = $cosmetic->is_new;
            $cosmetic->is_on_sale = $cosmetic->is_on_sale;
            $cosmetic->is_promoted = $cosmetic->is_promoted;
            
            return response()->json($cosmetic);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cosmético não encontrado'], 404);
        }
    }

    public function purchase(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Não autenticado'], 401);
            }

            $cosmetic = Cosmetic::findOrFail($id);
            
            if (!$cosmetic->price) {
                return response()->json(['message' => 'Este cosmético não está à venda'], 400);
            }

            $user->load('credit');
            $userCredits = $user->credit->amount ?? 0;

            if ($userCredits < $cosmetic->price) {
                return response()->json(['message' => 'Créditos insuficientes'], 400);
            }

            if ($user->cosmetics()->where('user_cosmetics.cosmetic_id', $id)->exists()) {
                return response()->json(['message' => 'Você já possui este cosmético'], 400);
            }

            DB::transaction(function () use ($user, $cosmetic, $id) {
                $user->cosmetics()->attach($cosmetic->id, [
                    'purchase_price' => $cosmetic->price,
                    'purchased_at' => now(),
                ]);

                $user->credit->decrement('amount', $cosmetic->price);
            });

            $user->refresh();
            $user->load(['credit', 'cosmetics']);

            return response()->json([
                'success' => true,
                'message' => 'Cosmético adquirido com sucesso!',
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao comprar cosmético'], 500);
        }
    }

    public function returnCosmetic(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Não autenticado'], 401);
            }

            $cosmetic = Cosmetic::findOrFail($id);

            $userCosmetic = $user->cosmetics()->where('user_cosmetics.cosmetic_id', $id)->first();
            
            if (!$userCosmetic) {
                return response()->json(['message' => 'Você não possui este cosmético'], 400);
            }

            $purchasePrice = $userCosmetic->pivot->purchase_price ?? $cosmetic->price ?? 0;
            $refundAmount = (int)$purchasePrice;

            \DB::transaction(function () use ($user, $id, $refundAmount) {
                $user->cosmetics()->detach($id);
                $user->credit->increment('amount', $refundAmount);
            });

            $user->refresh();
            $user->load(['credit', 'cosmetics']);

            return response()->json([
                'success' => true,
                'message' => 'Cosmético devolvido com sucesso!',
                'refund_amount' => $refundAmount,
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao devolver cosmético'], 500);
        }
    }

    public function owned(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Não autenticado'], 401);
            }

            $cosmetics = $user->cosmetics()->with('images')->get();

            $cosmetics->transform(function ($cosmetic) {
                if ($cosmetic->images) {
                    $cosmetic->images = $cosmetic->images->filter(function ($image) {
                        if (!$image->url) return false;
                        $url = trim((string) $image->url);
                        return $url !== '' && 
                               $url !== 'null' && 
                               $url !== 'undefined' && 
                               !str_contains(strtolower($url), 'undefined') &&
                               strlen($url) > 10;
                    })->values();
                }
                $cosmetic->is_owned = true;
                return $cosmetic;
            });

            return response()->json($cosmetics);
        } catch (\Exception $e) {
            return response()->json([], 200);
        }
    }
}

