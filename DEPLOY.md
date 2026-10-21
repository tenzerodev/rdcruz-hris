# Deploying the HRIS

This guide is for the agency's IT staff. It covers a Linux server (recommended) and a Windows/XAMPP
intranet server. Plan for about an hour the first time.

## 1. What you need

| | Minimum | For 500+ employees |
|---|---|---|
| Server | 2 CPU, 4 GB RAM, 40 GB disk | 4 CPU, 8 GB RAM, 100 GB disk |
| OS | Ubuntu 22.04/24.04 LTS, or Windows Server with XAMPP | |
| PHP | 8.3 or 8.4 with `pdo_mysql`, `mbstring`, `openssl`, `zip`, `fileinfo`, `gd`, `intl`, `bcmath` | |
| Database | MySQL 8 or MariaDB 10.4+ | |
| Web server | Nginx or Apache | |
| HTTPS certificate | **Required.** Phone and laptop cameras only work on HTTPS pages. Use the agency certificate, or Let's Encrypt for a public address. | |

The HRIS can run on the agency intranet only. Employees then punch from their phones only while on the office Wi-Fi.

## 2. Install (Linux)

```bash
# As root: packages
apt install nginx mariadb-server php8.4-fpm php8.4-{mysql,mbstring,xml,zip,gd,intl,bcmath,curl} unzip git
# Composer: https://getcomposer.org/download/

# Database
mysql -e "CREATE DATABASE hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
          CREATE USER 'hris'@'localhost' IDENTIFIED BY 'a-long-random-password';
          GRANT ALL ON hris.* TO 'hris'@'localhost';"

# Application (as the deploy user)
cd /var/www && git clone <your release> hris && cd hris     # or unzip the release package
composer install --no-dev --optimize-autoloader
cp .env.production.example .env        # then edit APP_URL and DB_*
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache

# Permissions: the web server writes only to storage/ and bootstrap/cache/
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

**Do not run `php artisan db:seed` in production.** The setup wizard makes the first administrator.

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name hris.agency.gov.ph;
    root /var/www/hris/public;
    index index.php;

    ssl_certificate     /etc/ssl/hris/fullchain.pem;
    ssl_certificate_key /etc/ssl/hris/privkey.pem;

    client_max_body_size 50M;   # CSV imports, 201 documents, backup uploads
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-Content-Type-Options nosniff;
    add_header Referrer-Policy strict-origin-when-cross-origin;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}
server { listen 80; server_name hris.agency.gov.ph; return 301 https://$host$request_uri; }
```

Set `upload_max_filesize = 50M` and `post_max_size = 50M` in `php.ini` to match.

### Scheduled tasks (required)

Backups, contract reminders, punch photo clean-up and face data deletion run
from the scheduler. Add **one** cron entry for the web server user (`crontab -u www-data -e`):

```
* * * * * cd /var/www/hris && php artisan schedule:run >> /dev/null 2>&1
```

System status shows "Scheduled tasks: OK" within 5 minutes once this works.

## 3. Install (Windows / XAMPP intranet)

1. Install XAMPP (PHP 8.3+), Composer and an HTTPS certificate for the server's name.
2. Copy the release to `C:\xampp\htdocs\hris`, then in a command prompt there:
   `composer install --no-dev`, `copy .env.production.example .env`, edit it, then
   `php artisan key:generate`, `php artisan migrate --force`, `php artisan storage:link`.
3. Point an Apache virtual host with SSL at `C:\xampp\htdocs\hris\public`.
4. Task Scheduler: a task that runs every minute:
   `C:\xampp\php\php.exe C:\xampp\htdocs\hris\artisan schedule:run`.

## 4. First run

1. Open the address. The **setup wizard** asks for the agency name, logo and the first system administrator.
2. You land on **System status**. Work down the list until everything is OK or understood.
3. **Email:** enter the agency mail server under Email and send a test.
4. **Security:** turn on "Require two-step verification for HR accounts".
5. **Privacy:** enter the Data Protection Officer's details; the privacy notice updates itself.
6. **User accounts:** make accounts for HR staff with the right role (HR officer, records, payroll,
   timekeeper, auditor). Avoid sharing the administrator account.
7. **Import data (CSV):** plantilla, employees, organization, leave balances, then holidays.
8. **Kiosks:** add each entrance device and activate it from that device.

`php artisan hris:check` runs the same checks from the command line (exit code 1 if something must be fixed).

## 5. Backups

- Nightly backups are on by default (1:00 AM, newest 14 kept) in `storage/app/private/backups/`.
- **Copy them off the server.** For example, a nightly `rsync` to the agency file server, or a weekly
  copy to an encrypted USB drive kept in a safe. A backup on the same disk does not survive a disk failure.
- Keep a copy of `.env` (with `APP_KEY`) with the backups, stored as securely. Backups made with one key
  can't be restored with another.
- Restore: System → Backups → Restore (type RESTORE and your password), or
  `php artisan hris:restore <file>`. A safety backup of the current data is made first.
- Test a restore on a spare machine at least once a year.

## 6. Updating

```bash
php artisan down
php artisan hris:backup          # always back up first
git pull   # or unzip the new release over the old one, keeping .env and storage/
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## 7. Security checklist

- [ ] HTTPS only; port 80 redirects to 443.
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`.
- [ ] Database user has rights to the HRIS database only; the database port is not open to the network.
- [ ] Two-step verification required for HR accounts.
- [ ] Server firewall allows only 443 (and SSH from the IT network).
- [ ] OS and PHP security updates applied monthly.
- [ ] Backups copied off the server and a restore tested.
- [ ] DPO details entered; the HRIS is listed in the agency's NPC registration of data processing systems.
- [ ] Audit log (Setup → Audit log) reviewed regularly. It records sign-ins, failed sign-ins, changes,
      exports, backup downloads and privacy requests.

## Several agencies on one server (optional)

One installation can host several agencies. Each has its own database, uploaded files (`storage/app/agencies/<id>`), cache and logins, and is chosen by the web address.

1. In `.env`: `HRIS_MULTI_AGENCY=true`. The database user must be allowed to create databases (or create them first and pass `--database=`).
2. Register the agency already on this server (keeps its database and files):
   `php artisan hris:agency:add citem --host=hris.citem.gov.ph --existing`
3. Add each new agency, then open its address to create its administrator:
   `php artisan hris:agency:add dti --host=hris.dti.gov.ph --name="Department of Trade and Industry"`
4. Point each address to this server (DNS) and give each an HTTPS certificate.
5. After every update: `php artisan hris:agency:run "migrate --force"`.

Scheduled tasks (backups, reminders, clean-up) run for every agency automatically. `php artisan hris:agency:list` shows them; `php artisan hris:agency:run "<command>" --agency=dti` runs a command for one.
