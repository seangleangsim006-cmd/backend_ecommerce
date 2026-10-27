<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    //
     // =========================
    // GET ALL PRODUCTS
    // =========================
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        $query = Product::with('restaurant');

        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Products retrieved successfully',
            'data' => $products
        ], 200);
    }

    // =========================
    // GET SINGLE PRODUCT
    // =========================
    public function show($id)
    {
        $product = Product::with('restaurant')->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Product retrieved successfully',
            'data' => $product
        ], 200);
    }

    // =========================
    // CREATE PRODUCT (ADMIN)
    // =========================
   public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'restaurant_id' => 'required|exists:users,id',
        'category_id' => 'required|exists:categories,id',
        'name' => 'required|string|max:255',
        'price' => 'required|numeric',
        'description' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        'is_available' => 'required|boolean',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 400);
    }

    // 1. get restaurant from merchant
    $restaurant = \App\Models\Restaurant::where('user_id', $request->merchant_id)->first();

    if (!$restaurant) {
        return response()->json([
            'message' => 'Merchant has no restaurant'
        ], 404);
    }

    // 2. verify category belongs to restaurant (IMPORTANT SECURITY)
    $category = \App\Models\Category::where('id', $request->category_id)
        ->where('restaurant_id', $restaurant->id)
        ->first();

    if (!$category) {
        return response()->json([
            'message' => 'Category does not belong to this merchant restaurant'
        ], 403);
    }

    // 3. upload image
    $imagePath = null;

    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('products', 'public');
    }

    // 4. create product
    $product = \App\Models\Product::create([
        'restaurant_id' => $restaurant->id,
        'category_id' => $category->id,
        'name' => $request->name,
        'price' => $request->price,
        'description' => $request->description,
        'image' => $imagePath,
        'is_available' => $request->is_available,
    ]);

    return response()->json([
        'message' => 'Product created successfully',
        'data' => $product->load('restaurant', 'category')
    ], 201);
}

    // =========================
    // UPDATE PRODUCT (ADMIN)
    // =========================
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'is_available' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $imagePath = $product->image;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product->update([
            'name' => $request->name ?? $product->name,
            'price' => $request->price ?? $product->price,
            'description' => $request->description ?? $product->description,
            'image' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Product updated successfully (admin)',
            'data' => $product
        ], 200);
    }

    // =========================
    // DELETE PRODUCT (ADMIN)
    // =========================
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully (admin)'
        ], 200);
    }
}
