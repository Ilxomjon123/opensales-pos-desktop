#!/usr/bin/env bash
set -euo pipefail

# ─── Dealer Bot Deploy Script ────────────────────────────
# Ishlatish: bash deploy/deploy.sh
# Yoki: chmod +x deploy/deploy.sh && ./deploy/deploy.sh

APP_DIR="/var/www/saleflow"
BRANCH="main"

echo "═══════════════════════════════════════════"
echo "  Dealer Bot — Deploy"
echo "═══════════════════════════════════════════"

cd "$APP_DIR"

# 1. Maintenance mode
echo "→ Maintenance mode yoqilmoqda..."
php artisan down --retry=60 --refresh=5 || true

# 2. Oxirgi kodni olish
echo "→ Git pull ($BRANCH)..."
git pull origin "$BRANCH"

# 3. PHP dependencies
echo "→ Composer install..."
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Fonts (self-hosted)
echo "→ Self-hosted fontlar tekshirilmoqda..."
bash deploy/download-fonts.sh

# 5. Node dependencies va build (SSR ham kiritilgan)
echo "→ NPM build (client + ssr)..."
npm ci --production=false
npm run build:ssr

# 5. Migration
echo "→ Migratsiyalar..."
php artisan migrate --force

# 6. Cache
echo "→ Cache yangilanmoqda..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Queue + SSR daemon restart
echo "→ Queue worker qayta ishga tushmoqda..."
php artisan queue:restart

# INERTIA_SSR_ENABLED — Laravel .env o'zgaruvchisi, bash sessiyaga avtomatik
# yuklanmaydi. .env dan o'qib olamiz, aks holda shart doimo false bo'lib qoladi
# va eski SSR daemon yangilangan chunk'larni topolmay ERR_MODULE_NOT_FOUND beradi.
INERTIA_SSR_ENABLED="$(grep -E '^INERTIA_SSR_ENABLED=' .env 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '"'\''[:space:]')"

if [ "${INERTIA_SSR_ENABLED:-false}" = "true" ]; then
    echo "→ Inertia SSR daemon qayta ishga tushmoqda..."
    sudo supervisorctl restart opensales-ssr || true
fi

# 8. Webhook lar
echo "→ Webhook larni ro'yxatga olish..."
php artisan webhook:setup

# 9. Maintenance mode o'chirish
echo "→ Maintenance mode o'chirilmoqda..."
php artisan up

echo ""
echo "═══════════════════════════════════════════"
echo "  ✓ Deploy muvaffaqiyatli yakunlandi!"
echo "═══════════════════════════════════════════"
