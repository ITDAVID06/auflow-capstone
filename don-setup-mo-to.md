# AUFlow Local Server VM Setup Guide (Ubuntu Desktop on Proxmox VE)

This guide provides the step-by-step commands to deploy AUFlow on a local virtual machine running Ubuntu Desktop within a Proxmox Virtual Environment (VE). This setup is optimized to run without Redis (using MySQL for queues and sessions) to minimize VM memory and resource overhead.

---

## Phase 1: Retrieve your VM's IP Address
1. Open the terminal on your Ubuntu Desktop VM.
2. Run:
   ```bash
   ip a
   ```
3. Locate the IP address next to `inet` under your main network card (e.g., `192.168.1.150` or similar). This will be referred to as `YOUR_VM_IP`.

---

## Phase 2: Install System Prerequisites
Update packages, add repositories for PHP 8.4 and Node.js 20, and install the required dependencies:

```bash
# 1. Update OS and install common tools
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common

# 2. Add Ondřej Surý PHP repository (for PHP 8.4 support)
sudo add-apt-repository ppa:ondrej/php -y

# 3. Add NodeSource repository for Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -

# 4. Refresh package list and install target stack
sudo apt update
sudo apt install -y php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-bcmath \
    php8.4-exif php8.4-pcntl php8.4-zip php8.4-curl php8.4-xml \
    mysql-server nginx supervisor git curl unzip nodejs
```

---

## Phase 3: Install Composer
Install Composer globally:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## Phase 4: Create MySQL Database
Log into your local MySQL instance:
```bash
sudo mysql -u root
```

Execute the following queries to create the database, database user, and grant privileges (replace `your_secure_password` with your password):

```sql
CREATE DATABASE auflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'auflow'@'127.0.0.1' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON auflow.* TO 'auflow'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

---

## Phase 5: Clone Project & Manage Permissions
Using a group ownership structure allows both you (the logged-in user) and the web server (`www-data`) to write to project files without running into permission issues:

```bash
# 1. Create target project folder
sudo mkdir -p /opt/auflow
sudo chown -R $USER:www-data /opt/auflow

# 2. Add your current VM user to the www-data web group
sudo usermod -aG www-data $USER
# NOTE: Log out and log back in, or run the command below to apply the group right away:
newgrp www-data

# 3. Clone the repository
git clone <repo-url> /opt/auflow
cd /opt/auflow
```

---

## Phase 6: Setup Environment Configuration (`.env`)
1. Copy the example config file:
   ```bash
   cp .env.example .env
   ```
2. Open `.env` in the terminal editor:
   ```bash
   nano .env
   ```
3. Update the configuration keys below (notably switching the queue/session configurations to use `database` instead of `redis`):

   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=http://YOUR_VM_IP                # The IP address from Phase 1 (e.g., http://192.168.1.150)

   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=auflow
   DB_USERNAME=auflow
   DB_PASSWORD=your_secure_password         # The password you set in Phase 4

   # Configured for database driver (removes Redis dependency)
   SESSION_DRIVER=database
   QUEUE_CONNECTION=database
   CACHE_STORE=database

   SNAPSHOT_STORAGE_DISK=local
   ```
   *Press `Ctrl + O` and `Enter` to save, and `Ctrl + X` to exit nano.*

---

## Phase 7: Install Dependencies & Run Database Seeders
Build and package the production environment:

```bash
# 1. Install PHP composer packages without dev tooling
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Install NPM packages and compile the React application
npm ci
npm run build

# 3. Generate Laravel security key
php artisan key:generate

# 4. Generate Snapshot verification signing key
KEY_VAL=$(php -r "echo bin2hex(random_bytes(32));")
echo "SNAPSHOT_SIGNING_KEY=${KEY_VAL}" >> .env

# 5. Populate and seed the database schema and default records
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force

# 6. Create symlink from storage/app/public to public/storage
php artisan storage:link

# 7. Enable system optimization caching
php artisan optimize

# 8. Set write access permissions for log and temporary storage paths
chmod -R 775 storage bootstrap/cache
```

---

## Phase 8: Configure Nginx Server Block
1. Create a server block config file:
   ```bash
   sudo nano /etc/nginx/sites-available/auflow
   ```
2. Paste the following configuration (replace `YOUR_VM_IP` with your actual VM IP address):
   ```nginx
   server {
       listen 80;
       server_name YOUR_VM_IP;
       root /opt/auflow/public;
       index index.php;

       add_header X-Frame-Options "SAMEORIGIN";
       add_header X-Content-Type-Options "nosniff";

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }

       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```
3. Enable the config, disable Nginx's default site, and reload the web server:
   ```bash
   sudo ln -s /etc/nginx/sites-available/auflow /etc/nginx/sites-enabled/
   sudo rm -f /etc/nginx/sites-enabled/default
   sudo nginx -t
   sudo systemctl restart nginx
   ```

---

## Phase 9: Configure Supervisor Workers
Set up Supervisor to run the Laravel schedule task and the database queue worker automatically:

1. **Queue Worker config**:
   ```bash
   sudo nano /etc/supervisor/conf.d/auflow-worker.conf
   ```
   Add:
   ```ini
   [program:auflow-queue]
   process_name=%(program_name)s_%(process_num)02d
   command=php /opt/auflow/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/opt/auflow/storage/logs/worker.log
   stopwaitsecs=3600
   ```

2. **Scheduler config**:
   ```bash
   sudo nano /etc/supervisor/conf.d/auflow-scheduler.conf
   ```
   Add:
   ```ini
   [program:auflow-scheduler]
   command=php /opt/auflow/artisan schedule:work
   autostart=true
   autorestart=true
   user=www-data
   redirect_stderr=true
   stdout_logfile=/opt/auflow/storage/logs/scheduler.log
   ```

3. **Start the services**:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start auflow-worker:*
   sudo supervisorctl start auflow-scheduler
   ```

---

## Phase 10: Access and Verify
Open any browser on a computer connected to your local home network (LAN/Wi-Fi) and navigate to:
`http://YOUR_VM_IP`
