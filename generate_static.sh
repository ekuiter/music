#!/usr/bin/env bash
set -euo pipefail

###
# CONFIG
###
PHP_HOST="127.0.0.1"
PHP_PORT="8000"
PHP_DOCROOT="."
BUILD_DIR="static"
BASE_URL="http://${PHP_HOST}:${PHP_PORT}"

###
# CLEAN PREVIOUS BUILD
###
mkdir -p "${BUILD_DIR}"
find "${BUILD_DIR}" -mindepth 1 -maxdepth 1 \
  ! -name "assets" \
  -exec rm -rf {} +

###
# INSTALL DEPENDENCIES
###
echo "📦 Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

echo "📦 Building frontend assets..."
npm install
npm run build

###
# START PHP SERVER
###
echo "🚀 Starting PHP server..."
php -S "${PHP_HOST}:${PHP_PORT}" -t "${PHP_DOCROOT}" >/dev/null 2>&1 &
PHP_PID=$!

# Ensure server is killed on exit (even if script fails)
cleanup() {
    echo "🛑 Stopping PHP server..."
    kill "${PHP_PID}" 2>/dev/null || true
}
trap cleanup EXIT

# Wait briefly for server to be ready
sleep 2

###
# CRAWL SITE
###
echo "🕷️  Crawling site and generating static HTML..."

wget \
  --mirror \
  --convert-links \
  --page-requisites \
  --no-parent \
  --execute robots=off \
  --directory-prefix="${BUILD_DIR}" \
  "${BASE_URL}"

###
# OPTIONAL: MOVE FILES UP ONE LEVEL
###
# wget nests output inside a host directory (e.g. static/127.0.0.1:8000)
CRAWLED_DIR="${BUILD_DIR}/${PHP_HOST}:${PHP_PORT}"

if [ -d "${CRAWLED_DIR}" ]; then
    echo "📁 Flattening output directory..."
    shopt -s dotglob
    mv "${CRAWLED_DIR}"/* "${BUILD_DIR}/"
    rm -rf "${CRAWLED_DIR}"
fi

# Remove any leftover static/ directory
rm -rf "${BUILD_DIR}/static"

echo "✅ Static site generated in ./${BUILD_DIR}"

echo "🗂️ Renaming query-based HTML files..."

find "${BUILD_DIR}" -maxdepth 1 -type f -name "index.html?p=*" | while read -r file; do
    base=$(basename "$file")
    page="${base#index.html?p=}"

    # ensure .html extension
    if [[ "$page" != *.html ]]; then
        page="${page}.html"
    fi

    # special case index
    if [ "$page" = "index.html" ]; then
        target="${BUILD_DIR}/index.html"
    else
        target="${BUILD_DIR}/${page}"
    fi

    echo "  → $base → $(basename "$target")"
    mv "$file" "$target"
done

echo "🔧 Rewriting query-based links to static paths..."

find "${BUILD_DIR}" -name "*.html" -type f -print0 | while IFS= read -r -d '' file; do
  # index.html?p=aurora → aurora.html
  sed -i '' -E 's/index\.html\?p=([^"&]+)/\1.html/g' "$file"

  # Replace static/assets/... → assets/...
  sed -i '' -E 's/static\/assets/assets/g' "$file"
done
