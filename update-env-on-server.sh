#!/bin/bash

# Configuration
REMOTE_USER="root"
REMOTE_HOST="45.93.139.96"
REMOTE_DIR="/opt/crm"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Function to display error messages
error() {
    echo -e "${RED}Error: $1${NC}"
    exit 1
}

echo -e "${YELLOW}🔄 Updating .env file on production server...${NC}"

# Check if .env file exists
if [ ! -f .env ]; then
    error "Local .env file not found. Please create it first."
fi

# Transfer .env file to the server
echo -e "${YELLOW}📤 Transferring .env file to server...${NC}"
scp .env ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}/.env || error "Failed to transfer .env file"

# Set correct permissions
echo -e "${YELLOW}🔒 Setting correct permissions...${NC}"
ssh ${REMOTE_USER}@${REMOTE_HOST} "chmod 600 ${REMOTE_DIR}/.env && chown www-data:www-data ${REMOTE_DIR}/.env" || error "Failed to set permissions"

echo -e "${GREEN}✅ .env file has been updated successfully!${NC}"
echo -e "${YELLOW}🔄 Restarting the application container...${NC}"

# Restart the container to apply changes
ssh ${REMOTE_USER}@${REMOTE_HOST} "cd ${REMOTE_DIR} && docker-compose restart app" || error "Failed to restart container"

echo -e "${GREEN}✅ Application has been restarted with the new .env configuration.${NC}"
