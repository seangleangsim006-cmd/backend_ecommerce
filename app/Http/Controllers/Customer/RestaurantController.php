<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;

class RestaurantController extends Controller
{
    public function index(): JsonResponse
    {
        $restaurants = Restaurant::withCount('products')->get();

        if ($restaurants->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No restaurants found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Restaurants retrieved successfully',
            'data' => $restaurants,
        ]);
    }
}
