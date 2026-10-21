# MyFitnessApp online zetten (eigen server)

Server: la-online.nl (Ubuntu 24.04, MySQL 8). Projectmap op de server: `/var/www/myfitnessapp`.
Vervang overal `fitness.la-online.nl` door het domein dat je kiest.

De app heeft nodig: **PHP 8.4 of nieuwer** (lokaal draai je 8.5), Composer, Node 22, nginx, MySQL en HTTPS (passkeys werken alleen via https).

---

## 0. Kijken wat er al op de server staat

```bash
bash deploy/server-check.sh
```

(of kopieer het script naar de server). Stuur de uitvoer naar Claude als je twijfelt welke stappen hieronder je kunt overslaan.

## 1. Server klaarmaken (eenmalig)

Sla over wat er al staat.

```bash
# PHP 8.5 met de extensies die Laravel nodig heeft
sudo add-apt-repository ppa:ondrej/php -y && sudo apt update
sudo apt install -y php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-xml php8.5-curl \
    php8.5-zip php8.5-bcmath php8.5-intl php8.5-gd unzip git nginx certbot python3-certbot-nginx

# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# Node 22
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

Upload-grootte voor foto's (in `/etc/php/8.5/fpm/php.ini`): `upload_max_filesize = 10M` en `post_max_size = 12M`, daarna `sudo systemctl restart php8.5-fpm`.

## 2. DNS

Maak bij je domeinbeheer een A-record `fitness` → het IP-adres van je server.

## 3. Database

```sql
CREATE DATABASE myfitnessapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'myfitnessapp'@'localhost' IDENTIFIED BY 'een-sterk-wachtwoord';
GRANT ALL PRIVILEGES ON myfitnessapp.* TO 'myfitnessapp'@'localhost';
-- Voor de import uit je eerste app (stap 9) alleen lezen:
GRANT SELECT ON `MyFitness`.* TO 'myfitnessapp'@'localhost';
FLUSH PRIVILEGES;
```

## 4. Code ophalen

```bash
sudo mkdir -p /var/www/myfitnessapp && sudo chown $USER:www-data /var/www/myfitnessapp
git clone https://github.com/lukeboy2002/MyFitnessApp.git /var/www/myfitnessapp
cd /var/www/myfitnessapp
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
```

Rechten, zodat PHP (www-data) mag schrijven:

```bash
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

## 5. `.env` invullen

```dotenv
APP_NAME="Lukeboy Fitness"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://fitness.la-online.nl
APP_LOCALE=nl

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myfitnessapp
DB_USERNAME=myfitnessapp
DB_PASSWORD=een-sterk-wachtwoord

ALLOW_REGISTRATION=true   # alleen de beginstand; daarna regelt een admin het in de app

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
LOG_LEVEL=warning

# Echte mail: nodig voor het bevestigen van je e-mailadres en wachtwoord vergeten.
# Dezelfde SMTP-gegevens als in je eerste app werken.
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="fitness@la-online.nl"
MAIL_FROM_NAME="${APP_NAME}"
```

Neem de overige regels over uit je lokale `.env` als je daar iets extra's hebt ingesteld (bv. `APP_LOCALE`).

## 6. Plaatjes kopiëren (staan niet in git)

Het logo en de plaatjes van oefeningen, lichaamsdelen en spiergroepen staan in `storage/app/public`. Kopieer ze **vóór het seeden** vanaf je Mac:

```bash
# op je Mac
rsync -av --exclude livewire-tmp --exclude profile-photos \
    ~/Herd/MyFitnessApp/storage/app/public/ jij@la-online.nl:/var/www/myfitnessapp/storage/app/public/
```

Op de server daarna nog een keer de rechten uit stap 4.

## 7. Database vullen

```bash
php artisan migrate --force
php artisan db:seed --force        # lichaamsdelen, spiergroepen, spieren en oefeningen
php artisan storage:link
php artisan optimize
```

Online (`APP_ENV=production`) maakt `db:seed` alleen lichaamsdelen, spiergroepen en oefeningen. De testaccounts en voorbeeldworkouts komen er niet bij, want daarvoor is Faker nodig en die installeert `composer install --no-dev` niet. Draai `db:seed` maar één keer.

## 8. nginx, HTTPS en queue

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/myfitnessapp     # domein en PHP-versie nakijken
sudo ln -s /etc/nginx/sites-available/myfitnessapp /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d fitness.la-online.nl

sudo cp deploy/myfitnessapp-queue.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now myfitnessapp-queue
```

Open nu https://fitness.la-online.nl, registreer je account (met hetzelfde e-mailadres als in je eerste app) en bevestig je e-mailadres.

Daarna registreren dichtzetten, zodat niemand anders een account kan maken. Maak jezelf eerst admin (zie stap 10) en zet registratie dicht via **Instellingen** in het menu (of `/admin/settings`). Daar kun je het later ook weer openzetten.

`ALLOW_REGISTRATION` in `.env` is alleen de beginstand zolang er nog niemand op de knop heeft gedrukt. Na een wijziging in `.env` altijd `php artisan optimize` draaien.

## 9. Je oude trainingen overzetten

Je eerste app gebruikt de database `MyFitness` op deze zelfde server, dus een dump is niet nodig. Maak vlak hiervoor nog je laatste training af in de oude app.

```bash
php artisan fitness:import-legacy antoine@la-online.nl --database=MyFitness --dry-run   # eerst kijken
php artisan fitness:import-legacy antoine@la-online.nl --database=MyFitness
```

Nog een keer draaien kan geen kwaad: wat al is overgezet, wordt overgeslagen.

## 10. Jezelf admin maken

Een admin mag de standaardoefeningen, lichaamsdelen, spiergroepen en spieren wijzigen en ziet in het menu **Gebruikers**.

```bash
php artisan fitness:admin antoine@la-online.nl            # admin maken
php artisan fitness:admin iemand@example.com --remove     # weer gewone gebruiker
```

---

## Later: een nieuwe versie online zetten

Lokaal committen en pushen naar GitHub, dan op de server:

```bash
cd /var/www/myfitnessapp && bash deploy/deploy.sh
```

Dat zet de site even in onderhoud, haalt de code op, installeert pakketten, bouwt CSS/JS, draait migrations en ververst de caches.

**Let op:** op de server nooit `migrate:fresh` draaien, dan ben je alles kwijt.

## Back-up (aanrader)

Dagelijkse dump via `crontab -e`:

```cron
0 3 * * * mysqldump --single-transaction myfitnessapp | gzip > /var/backups/myfitnessapp-$(date +\%u).sql.gz
```

(bewaart er 7, één per weekdag; zet de inloggegevens in `~/.my.cnf`).
