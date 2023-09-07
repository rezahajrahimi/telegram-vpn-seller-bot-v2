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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('bot_token')->default("5882116520:AAHfimkKsQEMHvZb9K3w_lIs_HF988_vQ9w");
            $table->string('bot_name')->default("@v2ray_vip_fast");
            $table->string('channel_id')->default("@FastV2rayVip");
            $table->string('admin_name')->default("reza");
            $table->string('admin_id')->default("0000");
            $table->string('panel_secret')->default("yukkbihb275Ui1LKeGpXSVw");
            $table->string('panel_type')->default("hiddyfy");
            $table->string('accunt_number')->default("6219861907131667");
            $table->string('tether_number')->default("");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
