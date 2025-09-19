# CRM Deployment Guide

## 🚀 Quick Deployment to VPS (45.93.139.96)

### Option 1: Automated Deployment Script

```bash
# Run the deployment script
./deploy.sh
```

### Option 2: Manual Deployment

#### 1. Prepare Local Files
```bash
# Build production assets
npm run build

# Create deployment archive (excluding dev files)
tar --exclude='.git' --exclude='node_modules' --exclude='vendor' --exclude='.env' --exclude='storage/logs/*' -czf crm-deployment.tar.gz .
```

#### 2. Upload to VPS
```bash
# Upload files
scp crm-deployment.tar.gz root@45.93.139.96:/tmp/

# SSH to VPS
ssh root@45.93.139.96
```

#### 3. Setup on VPS
```bash
# Create app directory
mkdir -p /var/www/crm
cd /var/www/crm

# Extract files
tar -xzf /tmp/crm-deployment.tar.gz
rm /tmp/crm-deployment.tar.gz

# Setup environment
cp env.production.example .env
nano .env  # Edit database credentials and settings

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci --only=production
npm run build

# Set permissions
chown -R www-data:www-data .
chmod -R 755 storage bootstrap/cache
chmod 644 .env

# Generate application key
php artisan key:generate
```

#### 4. Start Docker Services
```bash
# Start containers
docker-compose -f docker-compose.prod.yml up -d

# Run migrations
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Create admin user (optional)
docker-compose -f docker-compose.prod.yml exec app php artisan tinker
# User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);
```

## 🔧 Configuration

### Environment Variables (.env)
```env
APP_NAME="CRM System"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://45.93.139.96

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=crm
DB_USERNAME=laravel
DB_PASSWORD=YOUR_SECURE_PASSWORD

# Zone.eu API (already configured)
ZONE_EMAIL_API_URL="https://webfight.ee/zone-api/email_sender_api.php"
ZONE_EMAIL_API_TOKEN="YLLsJS0QmkvsJQwQNb_jHGR6aeQ1DCaRT53CYQH3qRVwfG4CMi0eVdUZ-JcSOb1J"
```

### Docker Services
- **CRM App**: http://45.93.139.96:8080 (port 8080)
- **MySQL**: Internal network + external port 3307

## 🛠 Management Commands

### View Logs
```bash
# Application logs
docker-compose -f docker-compose.prod.yml logs app

# Database logs
docker-compose -f docker-compose.prod.yml logs mysql
```

### Restart Services
```bash
# Restart all services
docker-compose -f docker-compose.prod.yml restart

# Restart specific service
docker-compose -f docker-compose.prod.yml restart app
```

### Update Application
```bash
# Stop services
docker-compose -f docker-compose.prod.yml down

# Upload new files and repeat deployment steps
# Then start services again
docker-compose -f docker-compose.prod.yml up -d
```

### Backup Database
```bash
# Create database backup
docker-compose -f docker-compose.prod.yml exec mysql mysqldump -u laravel -p crm > backup-$(date +%Y%m%d).sql
```

## 🔒 Security Notes

1. **Change default passwords** in .env file
2. **Setup firewall** to allow only necessary ports
3. **Enable SSL/HTTPS** for production use
4. **Regular backups** of database and files
5. **Monitor logs** for security issues

## 📊 System Requirements

- **Docker** and **Docker Compose** installed
- **Minimum 1GB RAM**
- **2GB disk space**
- **PHP 8.2** (handled by Docker)
- **MySQL 8.0** (handled by Docker)

## 🆘 Troubleshooting

### Permission Issues
```bash
sudo chown -R www-data:www-data /var/www/crm
sudo chmod -R 755 /var/www/crm/storage
```

### Database Connection Issues
```bash
# Check MySQL container
docker-compose -f docker-compose.prod.yml logs mysql

# Test database connection
docker-compose -f docker-compose.prod.yml exec app php artisan tinker
# DB::connection()->getPdo();
```

### Clear Cache
```bash
docker-compose -f docker-compose.prod.yml exec app php artisan cache:clear
docker-compose -f docker-compose.prod.yml exec app php artisan config:clear
docker-compose -f docker-compose.prod.yml exec app php artisan view:clear
```

## 📞 Support

For issues or questions, check:
1. Application logs: `docker-compose logs app`
2. Laravel logs: `storage/logs/laravel.log`
3. Apache logs: Inside container at `/var/log/apache2/`
