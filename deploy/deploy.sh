#!/usr/bin/env bash
# Nieuwe versie online zetten. Draai op de server vanuit de projectmap:
#   cd /var/www/myfitnessapp && bash deploy/deploy.sh
set -euo pipefail

cd "$(dirname "$0")/.."

echo "→ Onderhoudsmodus aan"
php artisan down --retry=15 || true

echo "→ Code ophalen"
git pull --ff-only origin main

echo "→ PHP-pakketten"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "→ CSS en JavaScript bouwen"
npm ci --no-audit --no-fund
npm run build

echo "→ Database bijwerken"
php artisan migrate --force

echo "→ Caches opbouwen"
php artisan storage:link 2>/dev/null || true
php artisan optimize

echo "→ Queue-worker herstarten"
php artisan queue:restart

echo "→ Onderhoudsmodus uit"
php artisan up

echo "✓ Klaar"
