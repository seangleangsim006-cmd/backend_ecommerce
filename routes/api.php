<?php

use App\Http\Controllers\Admin\RestaurantController as AdminRestaurantController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MerchantController as AdminMerchantController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Customer\ProductController as CustomerProductController;
use App\Http\Controllers\Merchant\RestaurantController;
use App\Http\Controllers\Merchant\CategoryController;
use App\Http\Controllers\Customer\RestaurantController as CustomerRestaurantController;
use App\Http\Controllers\Customer\CategoryController as CustomerCategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Customer\AddressController;
use App\Http\Controllers\FcmTokenController;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/phone/send-code', [AuthController::class, 'sendPhoneVerificationCode']);
Route::post('/phone/verify', [AuthController::class, 'verifyPhone']);
Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToSocialProvider']);
Route::get('/auth/{provider}/callback', [AuthController::class, 'handleSocialProviderCallback']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->put('/profile', [AuthController::class, 'updateProfile']);
Route::middleware('auth:sanctum')->get('/notifications', [NotificationController::class, 'index']);
Route::middleware('auth:sanctum')->put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
Route::middleware('auth:sanctum')->post('/fcm-tokens', [FcmTokenController::class, 'store']);
Route::middleware('auth:sanctum')->delete('/fcm-tokens/{token}', [FcmTokenController::class, 'destroy']);

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return response()->json([
        'success' => true,
        'data' => $request->user(),
    ]);
});




/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (Customer / Guest)
|--------------------------------------------------------------------------
*/


Route::prefix('customer')->group(function () {

    Route::get('/restaurants', [CustomerRestaurantController::class, 'index']);

    Route::get('/categories', [CustomerCategoryController::class, 'index']);

    Route::get('/products', [CustomerProductController::class, 'index']);

    Route::get('/products/{id}', [CustomerProductController::class, 'show']);

    Route::get('/restaurants/{restaurantId}/products', [CustomerProductController::class, 'byRestaurant']);

    Route::get('/categories/{categoryId}/products', [CustomerProductController::class, 'byCategory']);
});

Route::middleware('auth:sanctum')
    ->prefix('customer')
    ->group(function () {

        Route::get('/orders', [OrderController::class, 'index']);

        Route::get('/orders/history', [OrderController::class, 'history']);

        Route::post('/orders', [OrderController::class, 'store']);

        Route::get('/orders/{id}', [OrderController::class, 'show']);

        Route::put('/orders/{id}/cancel', [OrderController::class, 'cancel']);

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
        Route::put('/addresses/{id}/default', [AddressController::class, 'setDefault']);
    });




/*
|--------------------------------------------------------------------------
| MERCHANT ROUTES (Owner only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:merchant'])->prefix('merchant')->group(function () {

    // Restaurant (own only)
    Route::get('/restaurants', [RestaurantController::class, 'index']);
    Route::post('/restaurants', [RestaurantController::class, 'store']);
    Route::put('/restaurants/{id}', [RestaurantController::class, 'update']);
    Route::delete('/restaurants/{id}', [RestaurantController::class, 'destroy']);

    // Category (own restaurant only)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Product (own restaurant only)
    Route::get('/products', [App\Http\Controllers\Merchant\ProductController::class, 'index']);
    Route::get('/products/{id}', [App\Http\Controllers\Merchant\ProductController::class, 'show']);
    Route::post('/products', [App\Http\Controllers\Merchant\ProductController::class, 'store']);
    Route::put('/products/{id}', [App\Http\Controllers\Merchant\ProductController::class, 'update']);
    Route::delete('/products/{id}', [App\Http\Controllers\Merchant\ProductController::class, 'destroy']);

    // Orders (own restaurant only)
    Route::get('/orders', [App\Http\Controllers\Merchant\OrderController::class, 'index']);
    Route::get('/orders/{id}', [App\Http\Controllers\Merchant\OrderController::class, 'show']);
    Route::put('/orders/{id}/status', [App\Http\Controllers\Merchant\OrderController::class, 'updateStatus']);
});


/*
|--------------------------------------------------------------------------
| ADMIN ROUTES (SEE EVERYTHING)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // Dashboard overview
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    // Merchant management and merchant profile overview
    Route::get('/merchants', [AdminMerchantController::class, 'index']);
    Route::get('/merchants/{id}', [AdminMerchantController::class, 'show']);
    Route::put('/merchants/{id}/approve', [AdminMerchantController::class, 'approve']);
    Route::put('/merchants/{id}/reject', [AdminMerchantController::class, 'reject']);
    Route::put('/merchants/{id}/suspend', [AdminMerchantController::class, 'suspend']);
    Route::put('/merchants/{id}/reactivate', [AdminMerchantController::class, 'reactivate']);

    //Users
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
    Route::put('/users/{id}', [UserController::class, 'update']);

    // Restaurants
    Route::get('/restaurants', [App\Http\Controllers\Admin\RestaurantController::class, 'index']);
    Route::get('/restaurants/{id}', [App\Http\Controllers\Admin\RestaurantController::class, 'show']);
    Route::post('/restaurants', [App\Http\Controllers\Admin\RestaurantController::class, 'store']);
    Route::put('/restaurants/{id}', [App\Http\Controllers\Admin\RestaurantController::class, 'update']);
    Route::delete('/restaurants/{id}', [App\Http\Controllers\Admin\RestaurantController::class, 'destroy']);


    // // Categories
    // Route::get('/categories', [App\Http\Controllers\Admin\CategoryController::class, 'index']);



    // Products

    Route::get('/products', [\App\Http\Controllers\Admin\ProductController::class, 'index']);
    Route::get('/products/{id}', [\App\Http\Controllers\Admin\ProductController::class, 'show']);
    Route::post('/products', [\App\Http\Controllers\Admin\ProductController::class, 'store']);
    Route::put('/products/{id}', [\App\Http\Controllers\Admin\ProductController::class, 'update']);
    Route::delete('/products/{id}', [\App\Http\Controllers\Admin\ProductController::class, 'destroy']);

    // Orders
    Route::get('/orders', [\App\Http\Controllers\Admin\OrderController::class, 'index']);
    Route::get('/orders/export', [\App\Http\Controllers\Admin\OrderController::class, 'exportCsv']);
    Route::get('/orders/{id}', [\App\Http\Controllers\Admin\OrderController::class, 'show']);
    Route::put('/orders/{id}/status', [\App\Http\Controllers\Admin\OrderController::class, 'updateStatus']);
    Route::put('/orders/{id}/assign', [\App\Http\Controllers\Admin\OrderController::class, 'assignRestaurant']);
    Route::get('/restaurants/{id}/orders', [\App\Http\Controllers\Admin\OrderController::class, 'restaurantOrders']);
    Route::get('/orders/export', [OrderController::class, 'exportCsv']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});



Route::middleware('auth:sanctum')->prefix('customer')->group(function () {

    // Create KHQR for an order
    Route::post('/payment/khqr', [
        PaymentController::class,
        'createKHQR'
    ]);

    // Check KHQR payment status
    Route::get('/payment/{paymentId}/check', [
        PaymentController::class,
        'checkPayment'
    ]);

});
