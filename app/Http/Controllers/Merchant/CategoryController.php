<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Restaurant;
use App\Services\CloudinaryImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function index()
    {
        $restaurant = Restaurant::where('user_id', auth()->id())->first();

        if (!$restaurant) {
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }

        $categories = Category::where(
            'restaurant_id',
            $restaurant->id
        )->get();

        return response()->json([
            'message' => 'Categories retrieved successfully',
            'data' => $categories
        ], 200);
    }

    public function show($id)
    {
        $restaurant = Restaurant::where('user_id', auth()->id())->first();

        if (!$restaurant) {
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }

        $category = Category::where('id', $id)
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Category retrieved successfully',
            'data' => $category
        ], 200);
    }

    public function store(Request $request)
    {
        $validate = Validator($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validate->errors()
            ], 400);
        }

        $restaurant = Restaurant::where('user_id', auth()->id())->first();

        if (!$restaurant) {
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }

        $imagePath = app(CloudinaryImageService::class)
            ->upload($request->file('image'), 'categories');

        $category = Category::create([
            'name' => $request->name,
            'image' => $imagePath,
            'restaurant_id' => $restaurant->id,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $restaurant = Restaurant::where('user_id', auth()->id())->first();

        if (!$restaurant) {
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }

        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }

        if ($category->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'You are not allowed to update this category'
            ], 403);
        }

        $validate = Validator($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validate->errors()
            ], 400);
        }

        $oldImage = null;
        if ($request->hasFile('image')) {
            $oldImage = $category->image;
            $imagePath = app(CloudinaryImageService::class)
                ->upload($request->file('image'), 'categories');
            $category->image = $imagePath;
        }

        $category->name = $request->name;
        $category->save();

        if ($oldImage) {
            app(CloudinaryImageService::class)->delete($oldImage);
        }

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category
        ], 200);
    }

    public function destroy($id)
    {
        $restaurant = Restaurant::where('user_id', auth()->id())->first();

        if (!$restaurant) {
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }

        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }

        if ($category->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'You are not allowed to delete this category'
            ], 403);
        }

        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category because it contains products'
            ], 400);
        }

        app(CloudinaryImageService::class)->delete($category->image);

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ], 200);
    }
}