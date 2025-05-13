#!/bin/bash
# Weather API Server Setup Script
# This script helps set up a new server for Laravel deployment

# Exit if any command fails
set -e

echo "### Weather API Server Setup Script ###"
echo "This script will set up your server for the Laravel Weather API."

# Update system
echo "Updating system packages..."
sudo apt-get update && sudo apt-get upgrade -y

# Install required packages
echo "Installing required packages..."
sudo apt-get install -y nginx php8.2-fpm php8.2-cli php8.2-gd php8.2-curl php8.2-mbstring \
    php8.2-xml php8.2-zip php8.2-sqlite3 php8.2-mysql unzip git supervisor

# Configure PHP
echo "Configuring PHP..."
sudo sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 10M/' /etc/php/8.2/fpm/php.ini
sudo sed -i 's/post_max_size = 8M/post_max_size = 10M/' /etc/php/8.2/fpm/php.ini
sudo sed -i 's/memory_limit = 128M/memory_limit = 256M/' /etc/php/8.2/fpm/php.ini

# Configure Nginx
echo "Configuring Nginx..."
sudo cp weather-api.conf /etc/nginx/sites-available/
sudo ln -sf /etc/nginx/sites-available/weather-api.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx

# Set up app directory
APP_DIR=${1:-/var/www/weather-api}
echo "Setting up application directory: $APP_DIR"
sudo mkdir -p $APP_DIR
sudo chown -R $(whoami):www-data $APP_DIR
sudo chmod -R 755 $APP_DIR

# Configure Supervisor for queue workers
echo "Setting up Supervisor for queue workers..."
cat > laravel-worker.conf << EOF
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_DIR/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/worker.log
stopwaitsecs=3600
EOF

sudo mv laravel-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*

# Set up cronjob for Laravel scheduler
echo "Setting up cron job for Laravel scheduler..."
(crontab -l 2>/dev/null; echo "* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1") | sort - | uniq - | crontab -

echo "Setup completed successfully!"
echo "Next steps:"
echo "1. Copy your .env.production file to $APP_DIR/.env"
echo "2. Navigate to $APP_DIR and run:"
echo "   - php artisan key:generate"
echo "   - php artisan migrate --force"
echo "   - php artisan config:cache"
echo "   - php artisan route:cache"
echo "   - php artisan view:cache"
echo "3. Make sure to set up SSL with Let's Encrypt:"
echo "   - sudo apt install certbot python3-certbot-nginx"
echo "   - sudo certbot --nginx -d your-domain.com"