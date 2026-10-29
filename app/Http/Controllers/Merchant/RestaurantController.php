<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\CloudinaryImageService;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    // get all restaurants
    public function index()
    {
      $restaurant = Restaurant::where('user_id', auth()->id())->get();
        if(!$restaurant) {
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }
        return response()->json([
            'message' => 'Restaurant retrieved successfully',
            'data' => $restaurant
        ]);
    }

    // get restaurant by id
    public function show($id)
    {
        $restaurant = Restaurant::find($id);
        if (!$restaurant) {
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }
        return response()->json([
            'message' => 'Restaurant retrieved successfully',
            'data' => $restaurant
        ], 200);
    }
    // create restaurant for logged-in merchant
    public function store(Request $request)
    {
        $validate = Validator($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validate->errors()
            ], 400);
        }

        // check if restaurant already exists
        $exists = Restaurant::where('user_id', auth()->id())->first();

        if ($exists) {
            return response()->json([
                'message' => 'Restaurant already exists'
            ], 409);
        }

        $logoPath = null;

        if ($request->hasFile('logo')) {
            $logoPath = app(CloudinaryImageService::class)
                ->upload($request->file('logo'), 'restaurants');
        }

        $restaurant = Restaurant::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'address' => $request->address,
            'logo' => $logoPath,
        ]);

        return response()->json([
            'message' => 'Restaurant created successfully',
            'data' => $restaurant
        ], 201);
    }

    // get my restaurant
    public function myRestaurant()
    {
        $restaurant = Restaurant::where('user_id', auth()->id())->first();

        if (!$restaurant) {
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Restaurant retrieved successfully',
            'data' => $restaurant
        ]);
    }

    // update restaurant
    public function update(Request $request, $id)
    {
        $restaurant = Restaurant::where('id', $id)->where('user_id', auth()->id())->first();

        if (!$restaurant) {
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }

        $validate = Validator($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validate->errors()
            ], 400);
        }

        $logoPath = $restaurant->logo;

        if ($request->hasFile('logo')) {
            $logoPath = app(CloudinaryImageService::class)
                ->upload($request->file('logo'), 'restaurants');
        }

        $restaurant->update([
            'name' => $request->name,
            'address' => $request->address,
            'logo' => $logoPath,
        ]);

        return response()->json([
            'message' => 'Restaurant updated successfully',
            'data' => $restaurant
        ], 200);
    }

    public function destroy($id)
    {
        $restaurant = Restaurant::find($id);

        if (!$restaurant) {
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }

        // ADMIN: can delete anything
        if (auth()->user()->role === 'admin') {
            $restaurant->delete();

            return response()->json([
                'message' => 'Restaurant deleted successfully (admin)'
            ], 200);
        }

        // MERCHANT: can only delete own restaurant
        if ($restaurant->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $restaurant->delete();

        return response()->json([
            'message' => 'Restaurant deleted successfully'
        ], 200);
    }
}
