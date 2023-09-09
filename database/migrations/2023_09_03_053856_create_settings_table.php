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
            $table->string('bot_name')->default("@v2ray_vip_fast");
            $table->string('channel_id')->default("@FastV2rayVip");

            $table->string('panel_secret')->default("yukkbihb275Ui1LKeGpXSVw");
            $table->string('panel_type')->default("hiddyfy");
            $table->string('welcome_message')->default("سلامممممممم به ربات ما خوش آمدید.");

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
