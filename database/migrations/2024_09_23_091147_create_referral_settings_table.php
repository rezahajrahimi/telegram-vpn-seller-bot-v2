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
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            $table->text('description')->default('
            با ارسال این لینک به دوستان خود، با هر بار واریزی آنها، امتیاز بگیرید.
            ');
            $table->text('visit_card_text')->default('
                🔥فروش پروکسی اختصاصی با بروزترین پروتکل ها \r\n
                🏐 قابل استفاده در تلگرام و تمامی دستگاه ها به عنوان فیلترشکن \r\n
                ⏰ تجهیز شده با کانکشن هوشمند (بیش از 20 سرور برای هر کاربر) \r\n
                📬فاقد هر گونه تبلیغات! \r\n
                ✔️پشتیبانی ۲۴/۷ \r\n
                ♾بدون قطعی و کندی سرعت \r\n
                💰 خرید: \r\n
            ');
            $table->string('image_src', 255)->nullable()->default('text');
            $table->double('referral_percent', 15, 2)->default(10.0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_settings');
    }
};
