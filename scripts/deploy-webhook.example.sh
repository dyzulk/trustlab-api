#!/bin/bash

# =========================================================================
# TRUSTLAB DEPLOYMENT SCRIPT (EXAMPLE)
# =========================================================================
# CATATAN PENTING:
# Script ini adalah CONTOH/TEMPLATE untuk digunakan di aaPanel Webhook.
# Jangan jalankan script ini langsung dari repository jika belum dikonfigurasi.
#
# CARA PAKAI DI AAPANEL:
# 1. Buka App Store > Webhook (atau Git Manager di versi baru).
# 2. Add Webhook > Script.
# 3. Copy-paste isi file ini ke dalam kolom Script di aaPanel.
# 4. SESUAIKAN variable di bawah ini dengan konfigurasi server Anda.
# =========================================================================

# --- 1. KONFIGURASI SERVER (WAJIB DIEDIT DI AAPANEL) ---
# Ganti dengan path project Anda yang sebenarnya
PROJECT_PATH="/www/wwwroot/your-project.com"

# Ganti dengan path PHP binary Anda (sesuai versi php)
PHP_BIN="/www/server/php/83/bin/php"

# =========================================================================
# CONFIGURATION & ENVIRONMENT (JANGAN UBAH DI BAWAH INI KECUALI PAHAM)
# =========================================================================
export HOME=/root
export COMPOSER_HOME=/root/.composer
export PATH=$PATH:/usr/local/bin:/usr/bin:/bin

# --- CONFIG TELEGRAM ---
# Load from .env locally on server if available
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
fi

# Pastikan TELEGRAM_BOT_TOKEN dan TELEGRAM_CHAT_ID ada di .env server Anda
BOT_TOKEN="${TELEGRAM_BOT_TOKEN}"
CHAT_ID="${TELEGRAM_CHAT_ID}"

send_telegram() {
    local message="$1"
    if [ -n "$BOT_TOKEN" ] && [ -n "$CHAT_ID" ]; then
        curl -s -X POST "https://api.telegram.org/bot$BOT_TOKEN/sendMessage" \
            -d chat_id="$CHAT_ID" \
            -d text="$message" \
            -d parse_mode="HTML" > /dev/null
    else
        echo "⚠️ Telegram credentials missing, skipping notification."
    fi
}

# =========================================================================
# START DEPLOYMENT
# =========================================================================
echo "🚀 Starting Deployment..."
send_telegram "⏳ <b>Deployment Started</b>%0A%0A🚀 <b>Project:</b> TrustLab API%0A📅 <b>Date:</b> $(date)"

set -e

# Safety check directory
if [ ! -d "$PROJECT_PATH" ]; then
    echo "❌ Error: Project path $PROJECT_PATH does not exist."
    exit 1
fi

git config --global --add safe.directory "$PROJECT_PATH"
cd "$PROJECT_PATH"

trap 'send_telegram "❌ <b>Deployment FAILED!</b>%0A%0A⚠️ Check server logs untuk detail.%0A📅 <b>Date:</b> $(date)"; exit 1' ERR

# 3. Pull & Clean
echo "📥 Pulling latest code..."
git pull origin main

echo "🧹 Cleaning untracked files..."
git clean -fd

# 4. PHP Dependencies
echo "📦 Updating Composer dependencies..."
$PHP_BIN /usr/bin/composer install --no-dev --optimize-autoloader --no-interaction

# 5. Frontend Assets
echo "📦 Building frontend assets..."
npm install

echo "🔧 Fixing permissions..."
find node_modules -type f \( -path "*/bin/*" -o -path "*/.bin/*" \) -exec chmod +x {} \;
if [ -d "node_modules/@esbuild/linux-x64/bin" ]; then
    chmod +x node_modules/@esbuild/linux-x64/bin/esbuild
fi

rm -rf public/build
echo "🏗 Running Vite build..."
npx vite build

echo "🧹 Pruning dev dependencies..."
npm prune --omit=dev

# 6. Environment Setup
if [ -f .env.production.editable ]; then
    echo "📄 Updating .env from .env.production.editable..."
    cp .env.production.editable .env
elif [ ! -f .env ]; then
    cp .env.production.example .env
fi

# 7. Laravel Optimizations
echo "⚡ Optimizing Laravel..."
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan migrate --force

# NEW: Conditional CA Data Migration
# $PHP_BIN artisan ca:migrate-data

$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "✅ Deployment SUCCESS!"
send_telegram "✅ <b>Deployment Success!</b>%0A%0A📦 <b>Project:</b> TrustLab API%0A📅 <b>Date:</b> $(date)"
