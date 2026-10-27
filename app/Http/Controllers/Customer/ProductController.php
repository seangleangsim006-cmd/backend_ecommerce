<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Return a successful response.
     */
    private function successResponse(string $message, $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Return an error response.
     */
    private function errorResponse(string $message, int $status = 404): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    /**
     * GET /api/products
     */
    public function index(): JsonResponse
    {
        $products = Product::with(['restaurant', 'category'])->get();

        if ($products->isEmpty()) {
            return $this->errorResponse('No products found');
        }

        return $this->successResponse(
            'Products retrieved successfully',
            $products
        );
    }

    /**
     * GET /api/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['restaurant', 'category'])->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found');
        }

        return $this->successResponse(
            'Product retrieved successfully',
            $product
        );
    }

    /**
     * GET /api/restaurants/{restaurant_id}/products
     */
    public function byRestaurant(int $restaurant_id): JsonResponse
    {
        $products = Product::with(['restaurant', 'category'])
            ->where('restaurant_id', $restaurant_id)
            ->get();

        if ($products->isEmpty()) {
            return $this->errorResponse(
                'No products found for this restaurant'
            );
        }

        return $this->successResponse(
            'Products retrieved successfully',
            $products
        );
    }

    /**
     * GET /api/categories/{category_id}/products
     */
    public function byCategory(int $category_id): JsonResponse
    {
        $products = Product::with(['restaurant', 'category'])
            ->where('category_id', $category_id)
            ->get();

        if ($products->isEmpty()) {
            return $this->errorResponse(
                'No products found for this category'
            );
        }

        return $this->successResponse(
            'Products retrieved successfully',
            $products
        );
    }
}