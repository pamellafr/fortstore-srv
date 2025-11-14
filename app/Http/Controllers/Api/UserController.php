<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 20);
            $perPage = is_numeric($perPage) ? (int) $perPage : 20;
            $perPage = min(max($perPage, 1), 100);

            $query = User::query();

            if ($request->has('search') && ($search = trim($request->input('search')))) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->orderBy('name')
                ->paginate($perPage)
                ->withQueryString();

            $users->getCollection()->transform(function ($user) {
                $user->makeHidden(['password', 'remember_token', 'email_verified_at']);
                $cosmeticsCount = $user->cosmetics()->count();
                $user->cosmetics_count = $cosmeticsCount;
                return $user;
            });

            return response()->json($users);
        } catch (\Exception $e) {
            return response()->json(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0], 200);
        }
    }

    public function show($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->makeHidden(['password', 'remember_token', 'email_verified_at']);
            
            $cosmetics = $user->cosmetics()
                ->with('images')
                ->orderByPivot('purchased_at', 'desc')
                ->get();

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
                
                $cosmetic->purchase_price = $cosmetic->pivot->purchase_price ?? null;
                $cosmetic->purchased_at = $cosmetic->pivot->purchased_at ?? null;
                $cosmetic->is_owned = true;
                
                return $cosmetic;
            });

            return response()->json([
                'user' => $user,
                'cosmetics' => $cosmetics,
                'cosmetics_count' => $cosmetics->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }
    }

    public function purchaseHistory(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Não autenticado'], 401);
            }

            $perPage = $request->input('per_page', 20);
            $perPage = is_numeric($perPage) ? (int) $perPage : 20;
            $perPage = min(max($perPage, 1), 100);

            $query = $user->cosmetics()
                ->with('images')
                ->orderByPivot('purchased_at', 'desc');

            if ($request->has('search') && ($search = trim($request->input('search')))) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $cosmetics = $query->paginate($perPage)->withQueryString();

            $cosmetics->getCollection()->transform(function ($cosmetic) {
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
                
                $cosmetic->purchase_price = $cosmetic->pivot->purchase_price ?? null;
                $cosmetic->purchased_at = $cosmetic->pivot->purchased_at ?? null;
                $cosmetic->is_owned = true;
                
                return $cosmetic;
            });

            return response()->json($cosmetics);
        } catch (\Exception $e) {
            return response()->json(['data' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0], 200);
        }
    }
}

