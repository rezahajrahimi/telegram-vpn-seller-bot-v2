<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_images', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('account_id')->unsigned();
            $table->string('username');

            $table->bigInteger('transaction_id')->unsigned();
            $table->bigInteger('payment_type_id')->unsigned();
            $table->text('img_src')->nullable();

            $table
                ->foreign('transaction_id')
                ->references('id')
                ->on('transactions');
            $table
                ->foreign('payment_type_id')
                ->references('id')
                ->on('payment_types');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_images');
    }
};
