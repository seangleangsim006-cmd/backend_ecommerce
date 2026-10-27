<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RestaurantController extends Controller
{
    //
    public function index(){
        $restaurants = Restaurant::all();
        return response()->json([
            'message' => 'Restaurants retrieved successfully',
            'data' => $restaurants
        ], 200);
    }

    public function show($id){
        $restaurant = Restaurant::find($id);
        if(!$restaurant){
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }
        return response()->json([
            'message' => 'Restaurant retrieved successfully',
            'data' => $restaurant
        ], 200);
    }
    public function store(Request $request){
        $validate = Validator::make($request->all(), [
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
        
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos', 'public');
        }

        $restaurant = Restaurant::create([
            'name' => $request->name,
            'address' => $request->address,
            'logo' => $logoPath,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Restaurant created successfully',
            'data' => $restaurant
        ], 201);
    }
    public function update(Request $request, $id){
        $restaurant = Restaurant::find($id);
        if(!$restaurant){
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }
        $validate = Validator::make($request->all(), [
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
            $logoPath = $request->file('logo')->store('logos', 'public');
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
    public function destroy($id){
        $restaurant = Restaurant::find($id);
        if(!$restaurant){
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }
        $restaurant->delete();
        return response()->json([
            'message' => 'Restaurant deleted successfully'
        ], 200);
    }

}
