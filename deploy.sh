#!/bin/bash

# CRM Deployment Script for VPS
# Usage: ./deploy.sh

set -e

echo "🚀 Starting CRM deployment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
VPS_HOST="45.93.139.96"
VPS_USER="root"
APP_DIR="/var/www/crm"
BACKUP_DIR="/var/backups/crm"

echo -e "${YELLOW}📦 Creating deployment package...${NC}"

# Create temporary directory for deployment
TEMP_DIR=$(mktemp -d)
echo "Temporary directory: $TEMP_DIR"

# Copy project files (excluding development files)
rsync -av --exclude-from='.gitignore' \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='.env' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    . "$TEMP_DIR/"

# Create deployment archive
cd "$TEMP_DIR"
tar -czf crm-deployment.tar.gz .
cd - > /dev/null

echo -e "${GREEN}✅ Deployment package created${NC}"

echo -e "${YELLOW}📤 Uploading to VPS...${NC}"

# Upload deployment package
scp "$TEMP_DIR/crm-deployment.tar.gz" "$VPS_USER@$VPS_HOST:/tmp/"

echo -e "${GREEN}✅ Upload completed${NC}"

echo -e "${YELLOW}🔧 Deploying on VPS...${NC}"

# Execute deployment commands on VPS
ssh "$VPS_USER@$VPS_HOST" << 'EOF'
set -e

APP_DIR="/var/www/crm"
BACKUP_DIR="/var/backups/crm"

echo "Creating directories..."
mkdir -p "$APP_DIR"
mkdir -p "$BACKUP_DIR"

# Backup existing deployment if exists
if [ -d "$APP_DIR" ] && [ "$(ls -A $APP_DIR)" ]; then
    echo "Creating backup..."
    tar -czf "$BACKUP_DIR/crm-backup-$(date +%Y%m%d-%H%M%S).tar.gz" -C "$APP_DIR" .
fi

# Extract new deployment
echo "Extracting new deployment..."
cd "$APP_DIR"
tar -xzf /tmp/crm-deployment.tar.gz
rm /tmp/crm-deployment.tar.gz

# Set up environment file
if [ ! -f .env ]; then
    echo "Setting up environment file..."
    cp env.production.example .env
    
    # Generate APP_KEY
    echo "Generating application key..."
    php artisan key:generate --no-interaction
    
    echo "⚠️  IMPORTANT: Edit .env file with your database credentials!"
    echo "   - Set DB_PASSWORD"
    echo "   - Set MYSQL_ROOT_PASSWORD" 
    echo "   - Update other settings as needed"
fi

# Install dependencies
echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build assets
echo "Building assets..."
npm ci --only=production
npm run build

# Set permissions
echo "Setting permissions..."
chown -R www-data:www-data .
chmod -R 755 storage bootstrap/cache
chmod 644 .env

# Run migrations (only if database is configured)
echo "Database setup (run manually after configuring .env):"
echo "  docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force"
echo "  docker-compose -f docker-compose.prod.yml exec app php artisan db:seed --force"

echo "✅ Deployment completed!"
echo ""
echo "🔧 Next steps:"
echo "1. Edit $APP_DIR/.env with your database credentials"
echo "2. Start the application: cd $APP_DIR && docker-compose -f docker-compose.prod.yml up -d"
echo "3. Run migrations: docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force"
echo "4. Access your CRM at: http://45.93.139.96"

EOF

# Cleanup
rm -rf "$TEMP_DIR"

echo -e "${GREEN}🎉 Deployment completed successfully!${NC}"
echo ""
echo -e "${YELLOW}📋 Next steps on VPS:${NC}"
echo "1. SSH to VPS: ssh root@45.93.139.96"
echo "2. Go to app directory: cd /var/www/crm"
echo "3. Edit .env file: nano .env"
echo "4. Start application: docker-compose -f docker-compose.prod.yml up -d"
echo "5. Run migrations: docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force"
echo ""
echo -e "${GREEN}🌐 Your CRM will be available at: http://45.93.139.96${NC}"
