#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# ProgressOS — VPS deploy script
# Usage:  ./deploy.sh            → deploy latest tag
#         ./deploy.sh v2.9.0     → deploy specific tag
# ---------------------------------------------------------------------------

cd "$(dirname "$0")"

YELLOW='\033[1;33m'; GREEN='\033[1;32m'; RED='\033[1;31m'; NC='\033[0m'
step()  { echo -e "\n${YELLOW}▶ $1${NC}"; }
ok()    { echo -e "${GREEN}✓ $1${NC}"; }
abort() { echo -e "${RED}✗ $1${NC}" >&2; exit 1; }

# --- resolve target tag -------------------------------------------------------
git fetch --tags --quiet

if [ -n "${1-}" ]; then
  TARGET="$1"
else
  TARGET=$(git tag --sort=-creatordate | head -1)
  [ -z "$TARGET" ] && abort "No tags found in repository."
fi

CURRENT=$(git describe --tags --exact-match 2>/dev/null || git rev-parse --short HEAD)

echo -e "\n${GREEN}ProgressOS deploy${NC}"
echo "  Current : $CURRENT"
echo "  Target  : $TARGET"

if [ "$CURRENT" = "$TARGET" ]; then
  echo -e "${GREEN}Already on $TARGET — nothing to do.${NC}"
  exit 0
fi

# --- checkout -----------------------------------------------------------------
step "Checking out $TARGET"
git checkout "$TARGET" --quiet
ok "Checked out $TARGET"

# --- PHP dependencies ---------------------------------------------------------
step "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
ok "Composer done"

# --- Node / frontend ----------------------------------------------------------
step "Building frontend"
npm ci --silent
npm run build --silent
ok "Frontend built"

# --- Database -----------------------------------------------------------------
step "Running migrations"
php artisan migrate --force
ok "Migrations done"

# --- Laravel caches -----------------------------------------------------------
step "Caching config, routes, views"
php artisan config:cache
php artisan route:cache
php artisan view:cache
ok "Caches refreshed"

# --- Queue --------------------------------------------------------------------
step "Restarting queue workers"
php artisan queue:restart
ok "Queue restart signal sent"

# --- Optional: reload php-fpm (uncomment if applicable) ----------------------
# sudo systemctl reload php8.3-fpm && ok "php-fpm reloaded"

# --- Done ---------------------------------------------------------------------
echo -e "\n${GREEN}✓ Deployed $TARGET successfully.${NC}\n"
