<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Services\CloudinaryImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Get logged-in merchant restaurant
     */
    private function getRestaurant()
    {
        return Restaurant::where('user_id', auth()->id())->first();
    }

    /**
     * LIST PRODUCTS
     */
    public function index()
    {
        $restaurant = $this->getRestaurant();

        if (!$restaurant) {
            return response()->json([
                "message" => "Restaurant not found"
            ], 404);
        }

        $products = Product::where('restaurant_id', $restaurant->id)->get();

        return response()->json([
            "message" => "Products retrieved successfully",
            "data" => $products
        ], 200);
    }

    /**
     * SHOW PRODUCT
     */
    public function show($id)
    {
        $restaurant = $this->getRestaurant();

        $product = Product::where('id', $id)
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (!$product) {
            return response()->json([
                "message" => "Product not found"
            ], 404);
        }

        return response()->json([
            "message" => "Product retrieved successfully",
            "data" => $product
        ], 200);
    }

    /**
     * CREATE PRODUCT
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'category_id' => 'required|exists:categories,id',
            'is_available' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "message" => "Validation failed",
                "errors" => $validator->errors()
            ], 400);
        }

        $restaurant = Restaurant::where('user_id', auth()->id())->first();

        if (!$restaurant) {
            return response()->json([
                "message" => "Restaurant not found"
            ], 404);
        }

        // check category belongs to restaurant
        $category = Category::where('id', $request->category_id)
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (!$category) {
            return response()->json([
                "message" => "Category not found in your restaurant"
            ], 404);
        }

        $imagePath = app(CloudinaryImageService::class)
            ->upload($request->file('image'), 'products');

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'image' => $imagePath,
            'category_id' => $category->id,
            'restaurant_id' => $restaurant->id,
            'is_available' => $request->is_available,
        ]);

        return response()->json([
            "message" => "Product created successfully",
            "data" => $product
        ], 201);
    }

    /**
     * UPDATE PRODUCT
     */
    public function update(Request $request, $id)
    {
        $restaurant = $this->getRestaurant();

        $product = Product::where('id', $id)
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (!$product) {
            return response()->json([
                'message' => "Product not found",
                
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'stock' => 'nullable|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'category_id' => 'required|exists:categories,id',
            'is_available' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Validation failed",
                'errors' => $validator->errors()
            ], 400);
        }

        // validate category ownership
        $category = Category::where('id', $request->category_id)
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (!$category) {
            return response()->json([
                'message' => 'Category not found in your restaurant'
            ], 404);
        }

        // handle image
        $imagePath = $product->image;
        $oldImage = null;

        if ($request->hasFile('image')) {
            $oldImage = $product->image;
            $imagePath = app(CloudinaryImageService::class)
                ->upload($request->file('image'), 'products');
        }

        $product->update([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->filled('stock') ? $request->stock : null,
            'image' => $imagePath,
            'category_id' => $category->id,
            'restaurant_id' => $restaurant->id,
            'is_available' => $request->is_available,
        ]);

        if ($oldImage) {
            app(CloudinaryImageService::class)->delete($oldImage);
        }

        $product->refresh();

        return response()->json([
            'message' => "Product updated successfully",
            'data' => $product
        ], 200);
    }

    /**
     * DELETE PRODUCT
     */
    public function destroy($id)
    {
        $restaurant = $this->getRestaurant();

        $product = Product::where('id', $id)
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (!$product) {
            return response()->json([
                'message' => "Product not found"
            ], 404);
        }

        app(CloudinaryImageService::class)->delete($product->image);

        $product->delete();

        return response()->json([
            'message' => "Product deleted successfully"
        ], 200);
    }
}