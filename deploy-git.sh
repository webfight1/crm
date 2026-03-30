#!/bin/bash

# Git-based deployment script for CRM
# This script pushes code to git and pulls it on the server inside Docker container

# Configuration
REMOTE_USER="root"
REMOTE_HOST="45.93.139.96"
REMOTE_DIR="/opt/crm"
CONTAINER_NAME="crm-app"
GIT_BRANCH="main"  # Change to your branch name

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

echo -e "${YELLOW}🚀 Starting git-based deployment...${NC}"

# Step 1: Check if there are uncommitted changes
echo -e "${YELLOW}📝 Checking for uncommitted changes...${NC}"
if [[ -n $(git status -s) ]]; then
    echo -e "${YELLOW}⚠️  You have uncommitted changes. Please commit them first.${NC}"
    git status -s
    read -p "Do you want to commit all changes now? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        read -p "Enter commit message: " commit_message
        git add .
        git commit -m "$commit_message" || error "Failed to commit changes"
    else
        error "Deployment cancelled. Please commit your changes first."
    fi
fi

# Step 2: Push to git repository
echo -e "${YELLOW}📤 Pushing to git repository...${NC}"
git push origin $GIT_BRANCH || error "Failed to push to git repository"
echo -e "${GREEN}✅ Code pushed to git${NC}"

# Step 3: Pull code on server and restart container
echo -e "${YELLOW}🔄 Pulling code on server...${NC}"
ssh ${REMOTE_USER}@${REMOTE_HOST} << 'ENDSSH'
    set -e
    
    REMOTE_DIR="/opt/crm"
    CONTAINER_NAME="crm-app"
    GIT_BRANCH="main"
    
    echo "📂 Navigating to project directory..."
    cd ${REMOTE_DIR}
    
    # Check if git repository exists
    if [ ! -d ".git" ]; then
        echo "❌ Git repository not found in ${REMOTE_DIR}"
        echo "Please clone the repository first:"
        echo "  cd /opt && git clone <your-repo-url> crm"
        exit 1
    fi
    
    echo "🔄 Pulling latest code from git..."
    git fetch origin
    git reset --hard origin/${GIT_BRANCH}
    
    echo "📦 Installing/updating dependencies..."
    docker exec ${CONTAINER_NAME} composer install --no-dev --optimize-autoloader --no-interaction
    
    echo "🗄️  Running database migrations..."
    docker exec ${CONTAINER_NAME} php artisan migrate --force
    
    echo "🧹 Clearing caches..."
    docker exec ${CONTAINER_NAME} php artisan config:clear
    docker exec ${CONTAINER_NAME} php artisan cache:clear
    docker exec ${CONTAINER_NAME} php artisan view:clear
    docker exec ${CONTAINER_NAME} php artisan route:clear
    
    echo "🔄 Restarting container..."
    docker restart ${CONTAINER_NAME}
    
    echo "✅ Deployment completed successfully!"
ENDSSH

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
    echo -e "${GREEN}🌐 Your application is live at http://45.93.139.96:8082${NC}"
else
    error "Deployment failed on server"
fi
