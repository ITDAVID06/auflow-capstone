# AUFlow Local Server VM Setup Guide (Ubuntu Desktop on Proxmox VE)

This guide provides the step-by-step commands to deploy AUFlow on a local virtual machine running Ubuntu Desktop within a Proxmox Virtual Environment (VE). This setup is optimized to run without Redis (using MySQL for queues and sessions) to minimize VM memory and resource overhead.

---

## Phase 1: Create the VM in Proxmox

Before you can install AUFlow, you need a virtual machine. These steps assume you already have Proxmox VE installed and can access its web interface at `https://<proxmox-host>:8006`.

1. **Download the Ubuntu Desktop ISO** (24.04 LTS recommended) from [ubuntu.com/download/desktop](https://ubuntu.com/download/desktop) and upload it to your Proxmox storage via *Datacenter → Storage → ISO Images*.
2. **Create the VM** in the Proxmox web interface:
   - *Node → Create VM*
   - **General**: Give it a name (e.g., `auflow-server`).
   - **OS**: Select the uploaded Ubuntu Desktop ISO.
   - **System**: Leave defaults (SCSI controller, VirtIO SCSI single, OVMF (UEFI) if available).
   - **Disks**: At least **32 GB** (64 GB recommended).
   - **CPU**: At least **2 cores** (4 recommended).
   - **Memory**: At least **4 GB** (8 GB recommended).
   - **Network**: Leave defaults (VirtIO bridging to your Linux bridge, e.g., `vmbr0`).
3. **Start the VM** and complete the Ubuntu Desktop installation (language, keyboard, user account, etc.).
4. **After installation**, the VM will reboot into the Ubuntu Desktop. Log in with the user you created.

---

## Phase 2: Enable SSH Access

SSH allows you to manage the VM remotely from your workstation instead of always using the Proxmox console.

1. Open a terminal inside the VM (or use the Proxmox console).
2. Install and enable the OpenSSH server:

   ```bash
   sudo apt update
   sudo apt install -y openssh-server
   sudo systemctl enable --now ssh
   ```

3. Verify SSH is running:

   ```bash
   sudo systemctl status ssh
   ```

4. **Find the VM's current IP address** (you will need this to connect via SSH):

   ```bash
   ip a
   ```

   Look for the `inet` entry under your network interface (e.g., `192.168.1.150`).

5. **Test the SSH connection** from your workstation:

   ```bash
   ssh your-username@192.168.1.150
   ```

   Replace `your-username` with the user you created during Ubuntu installation and `192.168.1.150` with your VM's actual IP.

> **Note:** The remaining phases can be completed either through the VM's terminal directly or over SSH.

---

## Phase 3: Set a Static IP Address

By default, the VM gets a dynamic IP via DHCP, which can change after a reboot — breaking the `APP_URL` and any bookmarks. Assigning a static IP ensures the VM always responds at the same address.

### Option A: Static IP via Netplan (Ubuntu 24.04 default)

Ubuntu Desktop 24.04 uses **Netplan** with NetworkManager as the renderer.

1. Find your network interface name:

   ```bash
   ip a
   ```

   Look for an interface like `enp1s0`, `ens18`, or `eth0`.

2. Find your current network details (gateway, DNS):

   ```bash
   ip route | grep default
   resolvectl status
   ```

3. Create a Netplan configuration file:

   ```bash
   sudo nano /etc/netplan/01-network-manager-all.yaml
   ```

4. Replace the contents with the following (adjust interface name, IP, gateway, and DNS to match your network):

   ```yaml
   network:
     version: 2
     renderer: NetworkManager
     ethernets:
       enp1s0:                               # Replace with your interface name
         dhcp4: no
         addresses:
           - 192.168.1.150/24                # Replace with your desired static IP
         routes:
           - to: default
             via: 192.168.1.1                # Replace with your network gateway
         nameservers:
           addresses:
             - 192.168.1.1                   # Replace with your DNS server
             - 8.8.8.8
   ```

5. Apply the configuration:

   ```bash
   sudo netplan apply
   ```

6. Verify the new static IP:

   ```bash
   ip a
   ```

7. If you are connected via SSH, your connection will drop because the IP changed. Reconnect using the new static IP:

   ```bash
   ssh your-username@192.168.1.150
   ```

### Option B: Static IP via Proxmox (DHCP reservation)

If your Proxmox host or network router supports DHCP reservations, you can pin the VM's MAC address to a fixed IP from there instead. This avoids touching the VM's network config:

1. In the Proxmox web interface, go to your VM → *Hardware*.
2. Note the MAC address of the network device.
3. Add a DHCP reservation on your router or DHCP server mapping that MAC address to your desired IP.

This IP will be referred to as `YOUR_VM_IP` in the rest of this guide.

---

## Phase 4: Install System Prerequisites
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

## Phase 5: Install Composer
Install Composer globally:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## Phase 6: Create MySQL Database
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

## Phase 7: Clone Project & Manage Permissions
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

## Phase 8: Setup Environment Configuration (`.env`)
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
   APP_URL=http://YOUR_VM_IP                # The static IP from Phase 3 (e.g., http://192.168.1.150)

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

## Phase 9: Install Dependencies & Run Database Seeders
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

## Phase 10: Configure Nginx Server Block
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

## Phase 11: Configure Supervisor Workers
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

## Phase 12: Access and Verify
Open any browser on a computer connected to your local home network (LAN/Wi-Fi) and navigate to:
`http://YOUR_VM_IP`
