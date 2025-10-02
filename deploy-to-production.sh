#!/bin/bash

# Configuration
IMAGE_NAME="crm-app"
VERSION=$(date +%Y%m%d%H%M)  # Format: YYYYMMDDHHMM
REMOTE_USER="root"
REMOTE_HOST="45.93.139.96"
REMOTE_DIR="/opt/crm"
CONTAINER_NAME="crm-app"
DB_CONTAINER="crm-mysql"

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

echo -e "${YELLOW}🚀 Starting deployment of version ${VERSION}...${NC}"

# Step 1: Build the production image with version tag
echo -e "${YELLOW}🔨 Building Docker image with tag ${VERSION}...${NC}"
docker build --platform linux/amd64 -f Dockerfile.prod -t ${IMAGE_NAME}:${VERSION} . || error "Failed to build Docker image"

# Also tag as latest for convenience (optional)
docker tag ${IMAGE_NAME}:${VERSION} ${IMAGE_NAME}:latest

# Step 2: Save the image to a tar file
echo -e "${YELLOW}💾 Saving Docker image to tar file...${NC}"
docker save ${IMAGE_NAME}:${VERSION} -o ${IMAGE_NAME}-${VERSION}.tar || error "Failed to save Docker image"

# Step 3: Transfer the image to the VPS
echo -e "${YELLOW}📤 Transferring image to VPS...${NC}"
scp ${IMAGE_NAME}-${VERSION}.tar ${REMOTE_USER}@${REMOTE_HOST}:/tmp/ || error "Failed to transfer image to VPS"

# Step 4: On the VPS: Stop existing container, load new image, and start
echo -e "${YELLOW}🚀 Deploying to VPS...${NC}"
ssh ${REMOTE_USER}@${REMOTE_HOST} << ENDSSH
    # Load the Docker image
    echo "Loading Docker image version ${VERSION}..."
    docker load -i /tmp/${IMAGE_NAME}-${VERSION}.tar || { echo "Failed to load Docker image"; exit 1; }
    
    # Create necessary directories
    echo "Creating directories..."
    mkdir -p ${REMOTE_DIR}/storage/app/public \
        ${REMOTE_DIR}/storage/framework/{sessions,views,cache} \
        ${REMOTE_DIR}/bootstrap/cache || { echo "Failed to create directories"; exit 1; }
    
    # Set permissions
    echo "Setting permissions..."
    chown -R www-data:www-data ${REMOTE_DIR}/storage \
        && chmod -R 775 ${REMOTE_DIR}/storage \
        && chmod -R 775 ${REMOTE_DIR}/bootstrap/cache || { echo "Failed to set permissions"; exit 1; }
    
    # Stop and remove existing container if it exists
    if docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}\$"; then
        echo "Stopping and removing existing container..."
        docker stop ${CONTAINER_NAME} || true
        docker rm ${CONTAINER_NAME} || true
    fi
    
    # Run the new container
    echo "Starting new container with version ${VERSION}..."
    docker run -d \
        --name ${CONTAINER_NAME} \
        --network crm_network \
        -p 80:80 \
        -v ${REMOTE_DIR}/.env:/var/www/html/.env \
        -v ${REMOTE_DIR}/storage:/var/www/html/storage \
        -v ${REMOTE_DIR}/bootstrap/cache:/var/www/html/bootstrap/cache \
        --restart unless-stopped \
        ${IMAGE_NAME}:${VERSION} || { echo "Failed to start container"; exit 1; }
    
    # Clean up old images (keep last 3 versions)
    echo "Cleaning up old images..."
    docker images ${IMAGE_NAME} --format "{{.Tag}}" | sort -r | tail -n +4 | xargs -I {} docker rmi ${IMAGE_NAME}:{} 2>/dev/null || true
    
    # Clean up
    echo "Cleaning up..."
    rm -f /tmp/${IMAGE_NAME}-${VERSION}.tar
    docker system prune -f
    
    # Run database migrations
    echo "Running database migrations..."
    docker exec ${CONTAINER_NAME} php artisan migrate --force || { echo "Failed to run migrations"; exit 1; }
    
    echo -e "${GREEN}✅ Deployment of version ${VERSION} completed successfully!${NC}"
    
    # Show running container info
    echo -e "\n${YELLOW}🔄 Running container status:${NC}"
    docker ps --filter "name=${CONTAINER_NAME}" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
ENDSSH

# Local cleanup
echo -e "${YELLOW}🧹 Cleaning up local files...${NC}"
rm -f ${IMAGE_NAME}-${VERSION}.tar

echo -e "${GREEN}✨ Deployment process completed! Your application is now live at http://${REMOTE_HOST}${NC}"
echo -e "${YELLOW}📌 Deployed version: ${VERSION} (${IMAGE_NAME}:${VERSION})${NC}"