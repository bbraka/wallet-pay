<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Remove credit_note_number column
            $table->dropColumn('credit_note_number');
            
            // Add new columns
            $table->enum('order_type', ['internal_transfer', 'user_top_up', 'admin_top_up'])->after('status');
            $table->foreignId('receiver_user_id')->nullable()->constrained('users')->onDelete('cascade')->after('user_id');
            $table->foreignId('top_up_provider_id')->nullable()->constrained('top_up_providers')->onDelete('set null')->after('receiver_user_id');
            $table->string('provider_reference')->nullable()->after('top_up_provider_id');
        });
        
        // Update transactions table to allow negative amounts
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change(); // Already allows negatives, just ensuring precision
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Remove added columns
            $table->dropForeign(['top_up_provider_id']);
            $table->dropForeign(['receiver_user_id']);
            $table->dropColumn(['order_type', 'receiver_user_id', 'top_up_provider_id', 'provider_reference']);
            
            // Add back credit_note_number
            $table->string('credit_note_number')->unique()->nullable();
        });
    }
};