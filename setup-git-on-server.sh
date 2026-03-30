#!/bin/bash

# Setup git repository on server for git-based deployment
# This script needs to be run ONCE before using deploy-git.sh

# Configuration
REMOTE_USER="root"
REMOTE_HOST="45.93.139.96"
REMOTE_DIR="/opt/crm"
CONTAINER_NAME="crm-app"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}🔧 Setting up git repository on server...${NC}"
echo ""
echo "This script will:"
echo "  1. Backup current /opt/crm directory"
echo "  2. Initialize git repository in /opt/crm"
echo "  3. Add your git remote"
echo "  4. Pull latest code"
echo ""

# Ask for git repository URL
read -p "Enter your git repository URL (e.g., git@github.com:user/crm.git): " GIT_REPO_URL

if [ -z "$GIT_REPO_URL" ]; then
    echo -e "${RED}Error: Git repository URL is required${NC}"
    exit 1
fi

read -p "Enter git branch name (default: main): " GIT_BRANCH
GIT_BRANCH=${GIT_BRANCH:-main}

echo -e "${YELLOW}📦 Backing up and setting up git on server...${NC}"

ssh ${REMOTE_USER}@${REMOTE_HOST} << ENDSSH
    set -e
    
    REMOTE_DIR="/opt/crm"
    BACKUP_DIR="/opt/crm-backup-\$(date +%Y%m%d-%H%M%S)"
    
    echo "📂 Creating backup of current directory..."
    if [ -d "\${REMOTE_DIR}" ]; then
        cp -r "\${REMOTE_DIR}" "\${BACKUP_DIR}"
        echo "✅ Backup created at \${BACKUP_DIR}"
    fi
    
    echo "🔧 Initializing git repository..."
    cd \${REMOTE_DIR}
    
    # Initialize git if not already initialized
    if [ ! -d ".git" ]; then
        git init
        echo "✅ Git repository initialized"
    fi
    
    # Add remote
    if git remote | grep -q "origin"; then
        git remote set-url origin ${GIT_REPO_URL}
        echo "✅ Git remote updated"
    else
        git remote add origin ${GIT_REPO_URL}
        echo "✅ Git remote added"
    fi
    
    # Fetch from remote
    echo "📥 Fetching from remote..."
    git fetch origin
    
    # Reset to remote branch
    echo "🔄 Resetting to origin/${GIT_BRANCH}..."
    git reset --hard origin/${GIT_BRANCH}
    
    echo "✅ Git repository setup completed!"
    echo ""
    echo "Current git status:"
    git status
ENDSSH

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Git repository setup completed successfully!${NC}"
    echo ""
    echo -e "${GREEN}You can now use ./deploy-git.sh for deployments${NC}"
else
    echo -e "${RED}❌ Setup failed${NC}"
    exit 1
fi
