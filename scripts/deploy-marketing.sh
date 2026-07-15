#!/usr/bin/env bash
# Deploy قابلیت‌های فروش و بازاریابی PowerPS
# اجرا از ریشه پروژه backend:
#   bash scripts/deploy-marketing.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> Migration"
php artisan migrate --force

echo "==> Cache config"
php artisan config:cache

echo "==> Route list (marketing + promo)"
php artisan route:list --path=promo 2>/dev/null || true
php artisan route:list --path=marketing 2>/dev/null || true
php artisan route:list --path=abandoned 2>/dev/null || true

echo ""
echo "==> Deploy backend OK"
echo ""
echo "مراحل دستی روی سرور:"
echo "  1. Queue worker: php artisan queue:work --daemon (یا supervisor)"
echo "  2. Cron هر ۳۰ دقیقه:"
echo "     curl -s https://YOUR-DOMAIN/api/abandoned-cart-reminders"
echo "  3. انتشار Flutter: flow.txt → publish-powerps-webapp.sh"
echo "  4. تست: خرید، promo، تمدید، upsell، کرون abandoned cart"
