# Power Proxy Seller — هستهٔ ربات تلگرام (Laravel)

ربات **Power Proxy Seller** یک ابزار پیشرفته برای مدیریت و فروش کانفیگ‌های VPN/پروکسی است. این هسته با پنل‌های **Marzban**، **Hiddify**، **Sanaei** و **Pasarguard (پاسارگارد)** هماهنگ است، امکان **افزودن کانفیگ از فایل Excel** را دارد و از طریق ربات تلگرام و وب‌اپلیکیشن Flutter قابل مدیریت است.

---

# 🔓 آزادسازی پروژه پس از ۳ سال

بعد از **سه سال توسعه، نگه‌داری و استفاده خصوصی**، این پروژه اکنون به‌صورت کامل **آزاد (Open Source)** منتشر شده تا همهٔ توسعه‌دهندگان بتوانند روی آن فعالیت کنند، آن را گسترش دهند و به بهبود دسترسی کاربران ایرانی به اینترنت آزاد کمک کنند.

کد هستهٔ ربات که قبلاً **دیکد شده و غیرقابل ویرایش** بود، اکنون کاملاً آزاد است و می‌توانید به‌صورت مستقیم روی آن مشارکت داشته باشید:

## 📦 سورس‌های آزاد شده

### 🟦 هستهٔ ربات (Laravel)
سورس کامل هستهٔ ربات تلگرام برای مدیریت فروش VPN، اتصال به پنل‌ها، مدیریت کاربران و سفارش‌ها:

🔗 https://github.com/rezahajrahimi/telegram-vpn-seller-bot-v2

### 🟦 وب‌اپلیکیشن ربات (Flutter)
نسخهٔ وب‌اپلیکیشن برای مدیریت و تعامل با ربات، مناسب برای پنل‌های فروش و داشبوردهای مدیریتی:

🔗 https://github.com/rezahajrahimi/power_ps_front_3

این آزادسازی با هدف ایجاد یک اکوسیستم **باز، شفاف و قابل توسعه** انجام شده تا هر کسی بتواند در مسیر ساخت ابزارهای بهتر برای اینترنت آزاد نقش داشته باشد.

---

## ❤️ حمایت از توسعه پروژه

اگر می‌خواهید از ادامهٔ توسعه و نگه‌داری این پروژه حمایت کنید، می‌توانید از طریق شبکه‌های زیر کمک مالی ارسال کنید:

### 💠 TRON (TRC20)
`TRHjr9TrMWtdQxrH72bCg5LJ2XQU9PkQEL`

### 💠 Litecoin (LTC)
`ltc1qdapm3c45s6dngspmvh9wen52ymf7mt5hcyxkfm`

---

## ویژگی‌های کلیدی

- پشتیبانی از پنل‌های **Marzban**، **Hiddify**، **Sanaei** و **Pasarguard (پاسارگارد)**
- **افزودن کانفیگ از Excel** (xlsx/csv) برای موجودی انبار
- مدیریت کامل از طریق **وب‌اپلیکیشن Flutter**
- درگاه‌های پرداخت **زرین‌پال**، **NowPayments** و **Cryptomus**
- مدیریت کیف‌پول (تومانی، دلاری، همکاری)
- شخصی‌سازی متن‌ها، منوها و پیام‌های ربات
- مدیریت کاربران، سفارش‌ها، کانفیگ‌ها و تراکنش‌ها
- بازاریابی، کد تخفیف، کارت هدیه و زیرمجموعه‌گیری
- پشتیبان‌گیری خودکار و ارسال به تلگرام
- پیام‌رسانی خودکار (انقضا، مصرف، یادآوری)

---

## پیش‌نیازها

| ابزار | نسخه |
|--------|------|
| PHP | 8.4+ |
| Composer | 2.x |
| MySQL / MariaDB | 8.x+ |
| Node.js | 18+ (برای Vite) |
| Git | — |

**برای توسعهٔ وب‌اپ:** [Flutter SDK](https://docs.flutter.dev/get-started/install) (مخزن [`power_ps_front_3`](https://github.com/rezahajrahimi/power_ps_front_3))

---

## نصب سریع (سرور تولید)

برای Ubuntu 24.04 می‌توانید از اسکریپت نصب خودکار استفاده کنید:

```sh
sudo bash -c "$(curl -sL https://raw.githubusercontent.com/rezahajrahimi/powerps-core-scripts/refs/heads/main/install.sh)" @ install
```

این اسکریپت هسته (Laravel) و وب‌اپ (Flutter) را روی زیردامنه‌های جداگانه نصب می‌کند. پس از نصب، تنظیمات را در مسیر زیر ویرایش کنید:

```sh
/var/www/html/laravel-app/.env
```

---

## راه‌اندازی محلی (توسعه)

### ۱. کلون مخزن

```sh
git clone https://github.com/rezahajrahimi/telegram-vpn-seller-bot-v2.git
cd telegram-vpn-seller-bot-v2
```

### ۲. نصب وابستگی‌ها

```sh
composer install
cp .env.example .env
php artisan key:generate
```

### ۳. پایگاه داده

یک دیتابیس MySQL بسازید و مقادیر `DB_*` را در `.env` تنظیم کنید:

```sh
php artisan migrate
```

### ۴. دسترسی پوشه‌ها

```sh
chmod -R 775 storage bootstrap/cache
```

### ۵. اجرای سرور

```sh
php artisan serve
```

API روی `http://127.0.0.1:8000` در دسترس است.

### ۶. (اختیاری) دارایی‌های فرانت Laravel

```sh
npm install
npm run dev
```

### ۷. Cron و صف

برای عملکرد کامل (پشتیبان‌گیری، پیام‌های خودکار، صف خرید و …) scheduler لاراول باید هر دقیقه اجرا شود:

```sh
* * * * * cd /path/to/telegram-vpn-seller-bot-v2 && php artisan schedule:run >> /dev/null 2>&1
```

---

## تنظیمات `.env`

### اپلیکیشن

```env
APP_ENV=local          # در تولید: production
APP_DEBUG=true         # در تولید: false
APP_URL=http://127.0.0.1:8000
FRONT_URL=http://localhost:8080   # آدرس وب‌اپ Flutter
```

### پایگاه داده

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=powerps
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### ربات تلگرام

```env
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_API_ENDPOINT=https://api.telegram.org
TELEGRAM_ADMIN_ID=your_telegram_user_id
```

### درگاه زرین‌پال (اختیاری)

```env
ZARINPAL_MERCHANT_ID=your_merchant_id
ZARINPAL_SANDBOX_ENABLED=false
```

### NowPayments (اختیاری)

```env
NOWPAYMENTS_API_KEY=your_api_key
```

---

## تنظیم Webhook تلگرام

پس از بالا آمدن API، Webhook را ثبت کنید:

```
https://api.telegram.org/bot<BOT_TOKEN>/setWebhook?url=<APP_URL>/api/telegram/webhooks/inbound
```

مثال:

```
https://api.telegram.org/bot123456:ABC/setWebhook?url=https://your-domain.com/api/telegram/webhooks/inbound
```

---

## وب‌اپلیکیشن Flutter

پنل مدیریتی روی مخزن جداگانه قرار دارد:

```sh
git clone https://github.com/rezahajrahimi/power_ps_front_3.git
cd power_ps_front_3
flutter pub get
flutter run -d chrome
```

در تنظیمات وب‌اپ، آدرس API هسته (`APP_URL`) را وارد کنید. مقدار `FRONT_URL` در `.env` هسته باید با آدرس وب‌اپ یکسان باشد.

### ورود اولیه به پنل

| فیلد | مقدار |
|------|--------|
| نام کاربری | مقدار `TELEGRAM_ADMIN_ID` |
| رمز عبور | `admin123456` |

> پس از اولین ورود، رمز عبور را حتماً تغییر دهید.

---

## ساختار پروژه

```
app/
├── Console/          # دستورات Artisan و زمان‌بندی (Cron)
├── Http/Controllers/ # API و Webhook تلگرام
├── Jobs/             # کارهای پس‌زمینه (خرید، ارسال پیام، …)
├── Models/           # مدل‌های Eloquent
└── Services/         # منطق اصلی (TelegramBot، پرداخت، پنل‌ها، …)

routes/
├── api.php           # REST API برای وب‌اپ
└── web.php

database/migrations/  # اسکیمای دیتابیس
tests/                # تست‌های PHPUnit
```

---

## تست‌ها

```sh
./vendor/bin/phpunit
```

تست‌ها با SQLite in-memory اجرا می‌شوند (تنظیمات در `phpunit.xml`).

---

## مشارکت در توسعه

از مشارکت شما استقبال می‌کنیم! برای شروع:

1. مخزن را **Fork** کنید
2. یک شاخهٔ feature بسازید: `git checkout -b feature/my-feature`
3. تغییرات را commit کنید
4. Pull Request باز کنید

### راهنمای کد

- از [Laravel Pint](https://laravel.com/docs/pint) برای فرمت کد استفاده کنید: `./vendor/bin/pint`
- قبل از PR، تست‌ها را اجرا کنید
- برای ایده‌های بهبود UX و ساختار، فایل [`TODO.md`](TODO.md) را ببینید
- تغییرات بزرگ را ابتدا در Issue مطرح کنید

### حوزه‌های پیشنهادی برای مشارکت

- بهبود UX ربات (ویرایش پیام به‌جای ارسال پیام جدید)
- Refactor کنترلر Webhook تلگرام
- انتقال عملیات سنگین به Queue
- پوشش تست بیشتر
- مستندسازی API

---

## به‌روزرسانی

```sh
# پشتیبان از دیتابیس بگیرید
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## راه‌های ارتباطی

- وب‌سایت: [https://powerps.ir](https://powerps.ir)
- پشتیبانی تلگرام: [@powerproxysellersupport](https://t.me/powerproxysellersupport)
- توسعه‌دهنده / مشارکت: [@Rezahajrahimi_dev](https://t.me/Rezahajrahimi_dev)
- آموزش نصب (ویدیو): [YouTube](https://youtu.be/drZGXXxSNSE)

---

## مجوز

این پروژه تحت مجوز **MIT** منتشر شده است.
