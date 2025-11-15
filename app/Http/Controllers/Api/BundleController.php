<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cosmetic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BundleController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 20);
            $perPage = is_numeric($perPage) ? (int) $perPage : 20;
            $perPage = min(max($perPage, 1), 100);

            $query = Cosmetic::with(['bundleItems.images', 'images'])
                ->where('type_name', 'Item Bundle')
                ->has('bundleItems', '>=', 3)
                ->has('bundleItems', '<=', 6);

            $allBundles = $query->orderBy('created_at', 'desc')->get();

            $user = null;
            $userCosmeticIds = [];
            
            try {
                $bearerToken = $request->bearerToken();
                if ($bearerToken) {
                    $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);
                    if ($token && $token->tokenable) {
                        $user = $token->tokenable;
                        $userCosmetics = $user->cosmetics()->get();
                        $userCosmeticIds = $userCosmetics->pluck('id')->toArray();
                    }
                }
            } catch (\Exception $e) {
            }

            $allBundles->transform(function ($bundle) use ($userCosmeticIds, $user) {
                $bundle->is_bundle = true;
                $bundle->total_individual_price = $bundle->total_individual_price;
                $bundle->savings = $bundle->savings;
                
                $bundleItemsWithImages = $bundle->bundleItems->filter(function ($item) {
                    if (!$item->relationLoaded('images')) {
                        $item->load('images');
                    }
                    return $item->images && $item->images->count() > 0;
                });
                
                $bundleCosmeticIds = $bundleItemsWithImages->pluck('id')->toArray();
                
                if ($user && !empty($userCosmeticIds)) {
                    $ownedCount = count(array_intersect($bundleCosmeticIds, $userCosmeticIds));
                    $bundle->is_owned = $ownedCount === count($bundleCosmeticIds) && count($bundleCosmeticIds) > 0;
                    $bundle->owned_count = $ownedCount;
                    $bundle->total_items = count($bundleCosmeticIds);
                } else {
                    $bundle->is_owned = false;
                    $bundle->owned_count = 0;
                    $bundle->total_items = count($bundleCosmeticIds);
                }

                if (!$bundle->relationLoaded('images')) {
                    $bundle->load('images');
                }

                if ($bundle->images && $bundle->images->count() > 0) {
                    $filteredImages = $bundle->images->filter(function ($image) {
                        if (!$image || !$image->url) return false;
                        $url = trim((string) $image->url);
                        return $url !== '' && 
                               $url !== 'null' && 
                               $url !== 'undefined' && 
                               !str_contains(strtolower($url), 'undefined') &&
                               strlen($url) > 10;
                    })->values();
                    
                    if ($filteredImages->count() === 0 && $bundle->bundleItems && $bundle->bundleItems->count() > 0) {
                        $firstItem = $bundle->bundleItems->first();
                        if ($firstItem && $firstItem->images && $firstItem->images->count() > 0) {
                            $firstItemImage = $firstItem->images->filter(function ($img) {
                                if (!$img || !$img->url) return false;
                                $url = trim((string) $img->url);
                                return $url !== '' && $url !== 'null' && $url !== 'undefined';
                            })->first();
                            if ($firstItemImage) {
                                $bundle->images = collect([$firstItemImage]);
                            } else {
                                $bundle->images = collect();
                            }
                        } else {
                            $bundle->images = collect();
                        }
                    } else {
                        $bundle->images = $filteredImages;
                    }
                } elseif ($bundle->bundleItems && $bundle->bundleItems->count() > 0) {
                    $firstItem = $bundle->bundleItems->first();
                    if ($firstItem && $firstItem->images && $firstItem->images->count() > 0) {
                        $firstItemImage = $firstItem->images->filter(function ($img) {
                            if (!$img || !$img->url) return false;
                            $url = trim((string) $img->url);
                            return $url !== '' && $url !== 'null' && $url !== 'undefined';
                        })->first();
                        if ($firstItemImage) {
                            $bundle->images = collect([$firstItemImage]);
                        } else {
                            $bundle->images = collect();
                        }
                    } else {
                        $bundle->images = collect();
                    }
                } else {
                    $bundle->images = collect();
                }

                $bundle->bundleItems = $bundle->bundleItems->filter(function ($cosmetic) use ($userCosmeticIds) {
                    if (!$cosmetic->relationLoaded('images')) {
                        $cosmetic->load('images');
                    }
                    
                    if (!$cosmetic->images || $cosmetic->images->count() === 0) {
                        return false;
                    }
                    
                    $cosmetic->images = $cosmetic->images->filter(function ($image) {
                        if (!$image || !$image->url) return false;
                        $url = trim((string) $image->url);
                        return $url !== '' && 
                               $url !== 'null' && 
                               $url !== 'undefined' && 
                               !str_contains(strtolower($url), 'undefined') &&
                               strlen($url) > 10;
                    })->values();
                    
                    if ($cosmetic->images->count() === 0) {
                        return false;
                    }
                    
                    $cosmetic->is_owned = !empty($userCosmeticIds) && in_array($cosmetic->id, $userCosmeticIds);
                    
                    return true;
                })->values();
                
                return $bundle;
            });

            $filteredCollection = $allBundles->filter(function ($bundle) {
                return $bundle->bundleItems && $bundle->bundleItems->count() >= 3;
            })->values();

            $seenCosmeticIds = [];
            $seenNames = [];
            $uniqueBundles = $filteredCollection->filter(function ($bundle) use (&$seenCosmeticIds, &$seenNames) {
                $cosmeticId = $bundle->cosmetic_id;
                $bundleName = strtolower(trim($bundle->name ?? ''));
                
                if (in_array($cosmeticId, $seenCosmeticIds)) {
                    return false;
                }
                
                if (!empty($bundleName) && in_array($bundleName, $seenNames)) {
                    return false;
                }
                
                $seenCosmeticIds[] = $cosmeticId;
                if (!empty($bundleName)) {
                    $seenNames[] = $bundleName;
                }
                return true;
            })->values();

            $total = $uniqueBundles->count();
            $currentPage = $request->input('page', 1);
            $currentPage = is_numeric($currentPage) ? (int) $currentPage : 1;
            $currentPage = max(1, $currentPage);
            
            $offset = ($currentPage - 1) * $perPage;
            $paginatedItems = $uniqueBundles->slice($offset, $perPage)->values();
            
            $lastPage = max(1, (int) ceil($total / $perPage));
            
            $bundles = new \Illuminate\Pagination\LengthAwarePaginator(
                $paginatedItems,
                $total,
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json($bundles);
        } catch (\Exception $e) {
            return response()->json(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0], 200);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $bundle = Cosmetic::with(['bundleItems.images', 'images'])
                ->where('type_name', 'Item Bundle')
                ->has('bundleItems', '>=', 3)
                ->has('bundleItems', '<=', 6)
                ->findOrFail($id);
            
            $bundle->is_bundle = true;
            $bundle->total_individual_price = $bundle->total_individual_price;
            $bundle->savings = $bundle->savings;

            $user = null;
            $userCosmeticIds = [];
            
            try {
                $bearerToken = $request->bearerToken();
                if ($bearerToken) {
                    $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);
                    if ($token && $token->tokenable) {
                        $user = $token->tokenable;
                        $userCosmetics = $user->cosmetics()->get();
                        $userCosmeticIds = $userCosmetics->pluck('id')->toArray();
                    }
                }
            } catch (\Exception $e) {
            }

            if (!$bundle->relationLoaded('images')) {
                $bundle->load('images');
            }

            if ($bundle->images && $bundle->images->count() > 0) {
                $filteredImages = $bundle->images->filter(function ($image) {
                    if (!$image || !$image->url) return false;
                    $url = trim((string) $image->url);
                    return $url !== '' && 
                           $url !== 'null' && 
                           $url !== 'undefined' && 
                           !str_contains(strtolower($url), 'undefined') &&
                           strlen($url) > 10;
                })->values();
                
                if ($filteredImages->count() === 0 && $bundle->bundleItems && $bundle->bundleItems->count() > 0) {
                    $firstItem = $bundle->bundleItems->first();
                    if ($firstItem && $firstItem->images && $firstItem->images->count() > 0) {
                        $firstItemImage = $firstItem->images->filter(function ($img) {
                            if (!$img || !$img->url) return false;
                            $url = trim((string) $img->url);
                            return $url !== '' && $url !== 'null' && $url !== 'undefined';
                        })->first();
                        if ($firstItemImage) {
                            $bundle->images = collect([$firstItemImage]);
                        } else {
                            $bundle->images = collect();
                        }
                    } else {
                        $bundle->images = collect();
                    }
                } else {
                    $bundle->images = $filteredImages;
                }
            } elseif ($bundle->bundleItems && $bundle->bundleItems->count() > 0) {
                $firstItem = $bundle->bundleItems->first();
                if ($firstItem && $firstItem->images && $firstItem->images->count() > 0) {
                    $firstItemImage = $firstItem->images->filter(function ($img) {
                        if (!$img || !$img->url) return false;
                        $url = trim((string) $img->url);
                        return $url !== '' && $url !== 'null' && $url !== 'undefined';
                    })->first();
                    if ($firstItemImage) {
                        $bundle->images = collect([$firstItemImage]);
                    } else {
                        $bundle->images = collect();
                    }
                } else {
                    $bundle->images = collect();
                }
            } else {
                $bundle->images = collect();
            }

            $bundle->bundleItems = $bundle->bundleItems->filter(function ($cosmetic) use ($userCosmeticIds) {
                if (!$cosmetic->relationLoaded('images')) {
                    $cosmetic->load('images');
                }
                
                if (!$cosmetic->images || $cosmetic->images->count() === 0) {
                    return false;
                }
                
                $cosmetic->images = $cosmetic->images->filter(function ($image) {
                    if (!$image || !$image->url) return false;
                    $url = trim((string) $image->url);
                    return $url !== '' && 
                           $url !== 'null' && 
                           $url !== 'undefined' && 
                           !str_contains(strtolower($url), 'undefined') &&
                           strlen($url) > 10;
                })->values();
                
                if ($cosmetic->images->count() === 0) {
                    return false;
                }
                
                $cosmetic->is_owned = !empty($userCosmeticIds) && in_array($cosmetic->id, $userCosmeticIds);
                
                return true;
            })->values();

            $bundleCosmeticIds = $bundle->bundleItems->pluck('id')->toArray();
            
            if ($user && !empty($userCosmeticIds)) {
                $ownedCount = count(array_intersect($bundleCosmeticIds, $userCosmeticIds));
                $bundle->is_owned = $ownedCount === count($bundleCosmeticIds) && count($bundleCosmeticIds) > 0;
                $bundle->owned_count = $ownedCount;
                $bundle->total_items = count($bundleCosmeticIds);
            } else {
                $bundle->is_owned = false;
                $bundle->owned_count = 0;
                $bundle->total_items = count($bundleCosmeticIds);
            }

            return response()->json($bundle);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Bundle não encontrado'], 404);
        }
    }

    public function purchase(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Não autenticado'], 401);
            }

            $bundle = Cosmetic::with('bundleItems')
                ->where('type_name', 'Item Bundle')
                ->findOrFail($id);

            if ($bundle->bundleItems->isEmpty()) {
                return response()->json(['message' => 'Este bundle não possui itens'], 400);
            }

            $user->load('credit');
            $userCredits = $user->credit->amount ?? 0;

            if (!$bundle->price || $userCredits < $bundle->price) {
                return response()->json(['message' => 'Créditos insuficientes'], 400);
            }

            $bundleItemsWithImagesTemp = $bundle->bundleItems->filter(function ($item) {
                if (!$item->relationLoaded('images')) {
                    $item->load('images');
                }
                return $item->images && $item->images->count() > 0;
            });
            
            $bundleCosmeticIds = $bundleItemsWithImagesTemp->pluck('id')->toArray();
            $alreadyOwned = $user->cosmetics()->whereIn('cosmetics.id', $bundleCosmeticIds)->pluck('cosmetics.id')->toArray();

            DB::transaction(function () use ($user, $bundle, $bundleCosmeticIds, $alreadyOwned) {
                $purchaseTime = now();
                
                foreach ($bundleCosmeticIds as $cosmeticId) {
                    if (!in_array($cosmeticId, $alreadyOwned)) {
                        $cosmetic = Cosmetic::find($cosmeticId);
                        if ($cosmetic) {
                            $user->cosmetics()->attach($cosmeticId, [
                                'purchase_price' => $cosmetic->price ?? 0,
                                'purchased_at' => $purchaseTime,
                            ]);
                        }
                    }
                }

                $user->credit->decrement('amount', $bundle->price);
            });

            $user->refresh();
            $user->load(['credit', 'cosmetics']);

            return response()->json([
                'success' => true,
                'message' => 'Bundle adquirido com sucesso! Todos os itens foram adicionados ao seu inventário.',
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao comprar bundle: ' . $e->getMessage()], 500);
        }
    }
}
