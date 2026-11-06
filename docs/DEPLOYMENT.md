# MyVPR — Production Deployment Guide

This guide details the step-by-step procedure for deploying the MyVPR platform on an Ubuntu Linux server with Nginx, PHP 8.2+ FPM, MySQL 8.0, and Node.js 20 LTS.

---

## 1. System Requirements & Architecture

### Recommended Server Specifications
- **Operating System**: Ubuntu 22.04 LTS or 24.04 LTS
- **CPU**: 2 vCPUs minimum (4 vCPUs recommended for video transcoding/uploads)
- **RAM**: 4 GB minimum (8 GB recommended)
- **Disk Storage**: 50 GB+ NVMe SSD (scales with local video archive or use S3 for large assets)
- **Network**: 100 Mbps+ unmetered with HTTPS / TLS 1.3

### Required Runtimes & Packages
| Package | Version | Purpose |
| :--- | :--- | :--- |
| **PHP** | 8.1, 8.2, or 8.3 | API execution (`php-fpm`, `php-mysql`, `php-curl`, `php-mbstring`, `php-xml`, `php-zip`, `php-gd`) |
| **MySQL** | 8.0+ or MariaDB 10.6+ | Relational database engine |
| **Nginx** | 1.18+ | Web server, reverse proxy, SSL termination, and static asset cache |
| **Node.js** | 20 LTS or 22 LTS | Frontend build environment (Vite 8 compiler) |
| **Certbot** | Latest | Automated Let's Encrypt SSL/TLS certificates |

---

## 2. Server Provisioning

### Step 1: Install System Packages
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl git ufw unzip zip htop software-properties-common

# Install PHP 8.2 and required extensions
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-curl php8.2-mbstring \
                    php8.2-xml php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath

# Install Nginx and MySQL Server
sudo apt install -y nginx mysql-server

# Install Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### Step 2: Configure Firewall
```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw --force enable
```

---

## 3. Database Setup

### Step 1: Secure MySQL and Create Database
```bash
sudo mysql_secure_installation
```

Log in to MySQL as root:
```sql
sudo mysql -u root

-- Create Database
CREATE DATABASE myvpr_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create Dedicated User with Strong Password
CREATE USER 'myvpr_user'@'localhost' IDENTIFIED BY 'StrongRandomDatabasePassword!2026';

-- Grant Privileges
GRANT ALL PRIVILEGES ON myvpr_db.* TO 'myvpr_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 2: Import Initial Schema and Run Migrations
From the project root:
```bash
# Option A: Import standard master schema
mysql -u myvpr_user -p myvpr_db < database.sql

# Option B: Run automated migration runner
php run_db_migrations.php
```

---

## 4. Code Installation & Environment Configuration

### Step 1: Clone Repository
```bash
cd /var/www
sudo git clone https://github.com/destroyer19-ops/myvpr3.git myvpr
sudo chown -R www-data:www-data /var/www/myvpr
cd /var/www/myvpr
```

### Step 2: Configure Environment Variables
Copy template `.env.example` to production `.env`:
```bash
cp .env.example .env
cp api/.env.example api/.env
cp new/.env.example new/.env
```

Edit `/var/www/myvpr/.env` (and `api/.env`):
```dotenv
APP_ENV=production
APP_URL=https://myvpr.org
REACT_APP_URL=https://myvpr.org
SESSION_SECRET=create_a_64_character_random_hex_string_here

# Database Settings
DB_HOST=localhost
DB_USER=myvpr_user
DB_PASS=StrongRandomDatabasePassword!2026
DB_NAME=myvpr_db
DB_PORT=3306

# Squad Payment Gateway
SQUAD_ENV=production
SQUAD_PUBLIC_KEY=live_pk_...
SQUAD_SECRET_KEY=live_sk_...
SQUAD_WEBHOOK_SECRET=live_webhook_secret_...

# KingsChat Single Sign-On
KC_CLIENT_ID=your_registered_kc_client_id
KC_CLIENT_SECRET=your_registered_kc_client_secret
KC_REDIRECT_URI=https://myvpr.org/api/auth/kingschat/callback

# Amazon IVS & S3
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_REGION=eu-west-1
AWS_IVS_CHANNEL_ARN=arn:aws:ivs:eu-west-1:...
AWS_IVS_STREAM_KEY=sk_...
AWS_IVS_INGEST_ENDPOINT=rtmps://...
AWS_IVS_PLAYBACK_URL=https://...master.m3u8

# Brevo Transactional Email
BREVO_API_KEY=xkeysib-...
MAIL_FROM_EMAIL=noreply@myvpr.org
MAIL_FROM_NAME=MyVPR
```

---

## 5. Frontend Build Process

Build the React 19 application using Vite into `/var/www/myvpr/new/dist`:
```bash
cd /var/www/myvpr/new
npm ci
npm run build
```

Verify that the output directory `/var/www/myvpr/new/dist/index.html` and assets exist:
```bash
ls -la /var/www/myvpr/new/dist/
```

---

## 6. Directory Permissions & Storage

Ensure Nginx and PHP-FPM have appropriate read/write privileges:
```bash
sudo chown -R www-data:www-data /var/www/myvpr
sudo find /var/www/myvpr -type d -exec chmod 755 {} \;
sudo find /var/www/myvpr -type f -exec chmod 644 {} \;

# Writable Upload Directories
sudo chmod -R 775 /var/www/myvpr/uploads
sudo chmod -R 775 /var/www/myvpr/uploads/videos
sudo chmod -R 775 /var/www/myvpr/uploads/offerings
sudo chmod -R 775 /var/www/myvpr/uploads/thumbnails

# Protect .env files from public web access
chmod 600 /var/www/myvpr/.env
chmod 600 /var/www/myvpr/api/.env
```

---

## 7. Nginx Server Block Configuration

Create `/etc/nginx/sites-available/myvpr`:
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name myvpr.org www.myvpr.org;

    # Maximum upload size for video programs
    client_max_body_size 500M;

    # Root points to the compiled React SPA
    root /var/www/myvpr/new/dist;
    index index.html;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # 1. API Route -> Route to PHP Front Controller
    location /api/ {
        root /var/www/myvpr;
        try_files $uri $uri/ /api/index.php?$args;

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/run/php/php8.2-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/myvpr/api/index.php;
            include fastcgi_params;
            fastcgi_read_timeout 300;
        }
    }

    # 2. Uploaded Media Storage
    location /uploads/ {
        alias /var/www/myvpr/uploads/;
        # Disable PHP execution inside upload directories
        location ~ \.php$ {
            deny all;
        }
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    # 3. Static Assets Caching (Vite bundle)
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # 4. React Single Page App Router
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Deny access to sensitive files
    location ~ /\.(env|git|htaccess) {
        deny all;
        return 404;
    }
}
```

Enable site and restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/myvpr /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 8. SSL / TLS Certificate (Certbot)

Generate and auto-renew Let's Encrypt certificates:
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d myvpr.org -d www.myvpr.org
```

Test renewal dry-run:
```bash
sudo certbot renew --dry-run
```

---

## 9. Updating the Application

Create a deployment update script `/var/www/myvpr/deploy.sh`:
```bash
#!/usr/bin/env bash
set -e

echo "Deploying MyVPR update..."
cd /var/www/myvpr

# 1. Pull latest changes
git pull origin main

# 2. Run any database migrations
php run_db_migrations.php

# 3. Rebuild frontend
cd /var/www/myvpr/new
npm ci --prefer-offline
npm run build
cd /var/www/myvpr

# 4. Fix permissions
sudo chown -R www-data:www-data /var/www/myvpr
sudo chmod -R 775 /var/www/myvpr/uploads

# 5. Reload PHP-FPM and Nginx
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx

echo "Deployment finished successfully!"
```
Make executable: `chmod +x /var/www/myvpr/deploy.sh`.
