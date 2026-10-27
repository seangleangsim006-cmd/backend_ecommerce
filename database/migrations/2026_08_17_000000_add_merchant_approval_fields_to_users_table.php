add an admin dashboard with:
Total merchants/restaurants/products/orders
Today’s sales and order status counts
Recent orders<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing merchants are approved to avoid interrupting active businesses.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('merchant_status', 20)->default('approved');
            $table->text('merchant_status_reason')->nullable();
            $table->timestamp('merchant_status_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'merchant_status',
                'merchant_status_reason',
                'merchant_status_changed_at',
            ]);
        });
    }
};
