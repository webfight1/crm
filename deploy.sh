#!/bin/bash

# Deploy CRM to VPS via git (no Docker)
# Usage: ./deploy.sh [commit message]

REMOTE_USER="root"
REMOTE_HOST="45.93.139.96"
REMOTE_DIR="/opt/crm"
GIT_BRANCH="main"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Commit local changes if any
if [[ -n $(git status -s) ]]; then
    MSG="${1:-update}"
    echo -e "${YELLOW}Committing: $MSG${NC}"
    git add .
    git commit -m "$MSG" || { echo -e "${RED}Commit failed${NC}"; exit 1; }
fi

# Push to GitHub
echo -e "${YELLOW}Pushing to GitHub...${NC}"
git push origin $GIT_BRANCH || { echo -e "${RED}Push failed${NC}"; exit 1; }
echo -e "${GREEN}Pushed OK${NC}"

# Pull and update on VPS
echo -e "${YELLOW}Deploying on VPS...${NC}"
ssh ${REMOTE_USER}@${REMOTE_HOST} "
    set -e
    cd ${REMOTE_DIR}
    git fetch origin
    git reset --hard origin/${GIT_BRANCH}
    composer install --no-dev --optimize-autoloader --no-interaction -q
    npm ci --no-audit -q && npm run build
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    chown -R www-data:www-data storage bootstrap/cache public/build
    echo 'Deploy done!'
"

echo -e "${GREEN}Valmis! http://${REMOTE_HOST}:8082${NC}"
