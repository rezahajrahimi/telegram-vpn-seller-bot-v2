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
        Schema::create('agent_products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_categories_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->boolean('is_active')->default(true);
            $table->decimal('price', 8, 2)->nullable()->default(0.00);
            $table->decimal('price_in_dollar', 8, 2)->nullable()->default(0.00);
            $table->timestamps();
            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')->onDelete('cascade');
            $table
                ->foreign('product_categories_id')
                ->references('id')
                ->on('product_categories')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_products');
    }
};
