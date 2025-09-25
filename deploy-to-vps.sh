#!/bin/bash

# Deployment script for Laravel CRM to VPS
# Usage: ./deploy-to-vps.sh [server-ip] [ssh-port] [ssh-username]

set -e

# Check if required arguments are provided
if [ $# -lt 3 ]; then
    echo "Usage: $0 [server-ip] [ssh-port] [ssh-username]"
    exit 1
fi

SERVER_IP=$1
SSH_PORT=$2
SSH_USER=$3
REMOTE_DIR="/var/www/crm"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}🚀 Starting deployment to ${SERVER_IP}...${NC}"

# 1. Build the production Docker image locally
echo -e "${YELLOW}🛠  Building production Docker image...${NC}"
docker-compose -f docker-compose.prod.yml build

# 2. Save the Docker image to a tar file
echo -e "${YELLOW}💾 Saving Docker image...${NC}
docker save crm-crm-app:latest -o crm-app.tar

# 3. Create necessary directories on the VPS
echo -e "${YELLOW}📁 Creating directories on VPS...${NC}"
ssh -p $SSH_PORT ${SSH_USER}@${SERVER_IP} "
    sudo mkdir -p ${REMOTE_DIR}/{nginx,storage,storage/framework/{sessions,views,cache},bootstrap/cache} \
    && sudo chown -R ${SSH_USER}:www-data ${REMOTE_DIR} \
    && chmod -R 775 ${REMOTE_DIR}/storage \
    && chmod -R 775 ${REMOTE_DIR}/bootstrap/cache"

# 4. Copy necessary files to the VPS
echo -e "${YELLOW}📤 Uploading files to VPS...${NC}"
rsync -avz -e "ssh -p ${SSH_PORT}" \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='.env' \
    --exclude='node_modules' \
    --exclude='storage' \
    --exclude='vendor' \
    . ${SSH_USER}@${SERVER_IP}:${REMOTE_DIR}/

# 5. Copy the Docker image to the VPS
echo -e "${YELLOW}📦 Uploading Docker image...${NC}" 
scp -P $SSH_PORT crm-app.tar ${SSH_USER}@${SERVER_IP}:${REMOTE_DIR}/

# 6. On the VPS: load the Docker image, start the containers, and run migrations
echo -e "${YELLOW}🚀 Starting services on VPS...${NC}"
ssh -p $SSH_PORT ${SSH_USER}@${SERVER_IP} "
    cd ${REMOTE_DIR} \
    && docker load -i crm-app.tar \
    && rm crm-app.tar \
    && docker-compose -f docker-compose.prod.yml up -d \
    && echo -e '\n${YELLOW}Running database migrations...${NC}' \
    && docker-compose -f docker-compose.prod.yml exec -T crm-app php artisan migrate --force \
    && echo -e '\n${YELLOW}Optimizing application...${NC}' \
    && docker-compose -f docker-compose.prod.yml exec -T crm-app php artisan optimize:clear \
    && docker-compose -f docker-compose.prod.yml exec -T crm-app php artisan optimize \
    && docker-compose -f docker-compose.prod.yml exec -T crm-app php artisan storage:link"

echo -e "\n${GREEN}✅ Deployment completed successfully!${NC}"
echo -e "\n🌐 Your application should now be available at: http://${SERVER_IP}:8080"
echo -e "🔍 phpMyAdmin is available at: http://${SERVER_IP}:8082"

# 7. Clean up local files
echo -e "\n🧹 Cleaning up..."
rm crm-app.tar

echo -e "\n${GREEN}All done! Your Laravel CRM is now deployed to your VPS.${NC}"
