<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add payment completion date column
            $table->timestamp('payment_completion_date')->nullable()->after('updated_at');
        });

        // Update order_type enum to include withdrawal types
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type ENUM('internal_transfer', 'user_top_up', 'admin_top_up', 'user_withdrawal', 'admin_withdrawal') NOT NULL");
        
        // Update status enum to include pending_approval
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending_payment', 'completed', 'cancelled', 'refunded', 'pending_approval') NOT NULL");
        
        // Backfill payment_completion_date for existing completed orders
        DB::statement("UPDATE orders SET payment_completion_date = updated_at WHERE status = 'completed' AND payment_completion_date IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, remove any orders with the new enum values to avoid constraint errors
        DB::statement("DELETE FROM orders WHERE order_type IN ('user_withdrawal', 'admin_withdrawal')");
        DB::statement("DELETE FROM orders WHERE status = 'pending_approval'");
        
        Schema::table('orders', function (Blueprint $table) {
            // Remove payment completion date column
            $table->dropColumn('payment_completion_date');
        });

        // Revert order_type enum to original values
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type ENUM('internal_transfer', 'user_top_up', 'admin_top_up') NOT NULL");
        
        // Revert status enum to original values
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending_payment', 'completed', 'cancelled', 'refunded') NOT NULL");
    }
};
