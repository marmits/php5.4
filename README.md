> 🔐 Rappel : PHP5.4 est en fin de vie depuis 2015 — utilisez‑le uniquement en **conteneur isolé**, derrière un reverse proxy, et non exposé directement à Internet.

---

## 1) Dockerfile minimal (prêt à l’emploi)

Ce Dockerfile s’appuie sur le *flavour* **prod** (complet et simple à opérer) et charge vos overrides `php.ini` sans rien recompiler.

```dockerfile
# ./Dockerfile
FROM devilbox/php-fpm:5.4-prod

# Fichiers ini personnalisés (facultatif) :
# tout *.ini copié ici sera automatiquement chargé
COPY ./php-custom.d/ /etc/php-custom.d/

# Répertoire de travail aligné avec le volume monté (voir docker-compose)
WORKDIR /var/www/default/htdocs

# Rien d'autre à faire : l'image lance php-fpm sur 0.0.0.0:9000
# (Exposé par défaut, cf. doc Devilbox)
```

- Les *flavours* `-prod`, `-mods`, `-base`, etc. sont décrits ici (avec la liste des tags 5.4) : *devilbox/php-fpm* sur Docker Hub. [1](https://hub.docker.com/r/devilbox/php-fpm/)
- L’image *php-fpm-5.4* officielle de Devilbox expose FPM sur **9000** et fournit déjà une base d’extensions (ctype, curl, json, mbstring, mysqlnd, pdo_sqlite, etc.). [2](https://hub.docker.com/r/devilbox/php-fpm-5.4/)

---

## 2) Exemple `docker-compose.yml`

On mappe FPM sur l’hôte en **9004** (pour éviter tout conflit si vous avez déjà un php-fpm local). On monte également le code et on active quelques modules “legacy”.

```yaml
# ./docker-compose.yml
version: "3.8"

services:
  php54-fpm:
    image: devilbox/php-fpm:5.4-prod
    # ou : build: .  si vous utilisez le Dockerfile ci-dessus
    ports:
      - "127.0.0.1:9004:9000"      # FPM accessible en local via 127.0.0.1:9004
    environment:
      TIMEZONE: "Europe/Paris"     # cf. variables supportées par l'image
      NEW_UID: "1000"              # aligne l'UID conteneur sur votre user host
      NEW_GID: "1000"
      # Active explicitement quelques extensions souvent requises par du legacy
      # (disponibles via l'image Devilbox) :
      ENABLE_MODULES: "mysql,mysqli,pdo_mysql,mcrypt,gd,intl,soap,zip"
      # Vous pouvez désactiver des modules si besoin :
      # DISABLE_MODULES: "xdebug"
    volumes:
      - ./app:/var/www/default/htdocs:rw
      - ./php-custom.d:/etc/php-custom.d:ro
    restart: unless-stopped
```

> Les variables **TIMEZONE**, **NEW_UID/GID**, **ENABLE_MODULES/DISABLE_MODULES**, les volumes `/etc/php-custom.d` et les ports exposés sont documentés sur la page de l’image *devilbox/php-fpm*. [1](https://hub.docker.com/r/devilbox/php-fpm/)

---

## 3) `php.ini` personnalisé (facultatif)

Créez `./php-custom.d/99-overrides.ini` :

```ini
; ./php-custom.d/99-overrides.ini
date.timezone = Europe/Paris
display_errors = On
error_reporting = E_ALL & ~E_DEPRECATED
memory_limit = 256M
upload_max_filesize = 32M
post_max_size = 32M
max_execution_time = 120
; Important pour PHP-FPM en conteneur : logs vers stdout/stderr
; (souvent déjà géré par l'image, mais on explicite) :
; error_log = /proc/self/fd/2
```

> L’image charge automatiquement les *.ini* placés dans `/etc/php-custom.d/`. [1](https://hub.docker.com/r/devilbox/php-fpm/)
> Les fichiers *.ini* dans `/etc/php-custom.d` sont automatiquement chargés par l’image Devilbox. [1](https://docs.vultr.com/how-to-install-php-and-php-fpm-on-debian-12)

---

## 4) Lancer & tester

```bash
docker compose up -d
docker compose exec php54-fpm php -v
docker compose exec php54-fpm php -m | egrep -i 'mysql|mysqli|pdo|mcrypt|gd|intl|soap|zip'
```

Créez un petit `./app/info.php` :

```php
<?php phpinfo();
```

---

## 5) Configuration **Apache2** (dans WSL2)

### a) Activer les modules nécessaires

```bash
sudo a2enmod proxy proxy_fcgi setenvif
# (désactive mod_php s'il est chargé)
sudo a2dismod php8.2 php8.1 php7.4 2>/dev/null || true
sudo service apache2 reload
```

> Apache 2.4 + **mod_proxy_fcgi** est la voie standard pour parler à PHP‑FPM (TCP ou socket). Les exemples officiels montrent l’usage de **ProxyPassMatch** (ou SetHandler) pour router *.php* vers le backend FPM. [3](https://httpd.apache.org/docs/2.4/mod/mod_proxy_fcgi.html)[4](https://cwiki.apache.org/confluence/display/HTTPD/PHP-FPM)

### b) Vhost Apache : proxy vers FPM du conteneur

**Important** : comme le FPM tourne en conteneur avec son **propre** système de fichiers, on utilise **ProxyPassMatch** pour *réécrire le chemin de script* vers le chemin **dans** le conteneur (`/var/www/default/htdocs`). C’est exactement le pattern recommandé par la doc Apache. [3](https://httpd.apache.org/docs/2.4/mod/mod_proxy_fcgi.html)

Crée `/etc/apache2/sites-available/legacy-php54.conf` :

```apache
<VirtualHost *:61610>
    ServerName legacy54
    DocumentRoot /path/vers/ton-projet/app

    <Directory /path/vers/ton-projet/app>
        AllowOverride All
        Require all granted
        Options FollowSymLinks
    </Directory>

    # Ne laisse pas Apache essayer d’exécuter du PHP lui-même
    <FilesMatch "\.phps$">
        Require all denied
    </FilesMatch>
    <FilesMatch "^\.ph(p[3457]?|t|tml)$">
        Require all granted
    </FilesMatch>

    # La ligne clef : mappe toute URL *.php vers le chemin PHP à l'intérieur du conteneur
    # (qui a /var/www/default/htdocs monté)
    ProxyPassMatch "^/(.*\.php(/.*)?)$" "fcgi://127.0.0.1:9004/var/www/default/htdocs/"

    # (Optionnel) Augmente si tu as des scripts lents
    ProxyTimeout 300
    DirectoryIndex index.php index.html
    ErrorLog ${APACHE_LOG_DIR}/legacy-php54_error.log
    CustomLog ${APACHE_LOG_DIR}/legacy-php54_access.log combined
</VirtualHost>
```

Active le site et recharge :
```bash
sudo a2ensite legacy-php54
sudo service apache2 reload
```

- L’usage de **ProxyPassMatch ... "fcgi://IP:PORT/DOCROOT/"** est l’exemple officiel conseillé pour **PHP‑FPM** (ça évite le classique *“No input file specified”* dû aux chemins différents entre hôte et conteneur). [3](https://httpd.apache.org/docs/2.4/mod/mod_proxy_fcgi.html)
- Si tu préfères `SetHandler`, il faut que **le même chemin disque** existe à l’identique côté conteneur, ce qui est plus fragile. La page “PHP‑FPM (HTTPD)” détaille aussi le mode “Proxy via handler”. [4](https://cwiki.apache.org/confluence/display/HTTPD/PHP-FPM)

---
- /etc/hosts
> 127.0.0.1 legacy54

Sur l'hôte windows
C:\WINDOWS\system32\drivers\etc\hosts
172.19.238.71 legacy54

---

## 6) Pourquoi Devilbox (et pas `php:5.4-fpm`) ?

- Les **images officielles Docker PHP 5.x** ne sont **plus maintenues**, et les bases Debian “Jessie” ont été déplacées sur les archives (APT devient pénible à utiliser). La team officielle a explicitement fermé les issues en ce sens. [3](https://github.com/docker-library/php/issues/1396)
- Il reste des vestiges comme `php:5.4-apache` (amd64 uniquement, très ancien), mais rien de sain/maintenu côté FPM — d’où l’intérêt d’images communautaires actives type Devilbox. [4](https://hub.docker.com/layers/library/php/5.4-apache/images/sha256-298f2295509309262b0daaa27e15e3682437d0210128f66d482381255b907582)
- Devilbox fournit des **tags 5.4** dans différents *flavours* (*base/mods/prod/work/slim*), des **variables** pour activer/désactiver des modules, et une **liste d’extensions** large (dont `mysql`, `mysqli`, `pdo_mysql`, `mcrypt`, `gd`, `intl`, `soap`, `zip`), ce qui simplifie l’exécution d’apps “legacy”. [1](https://hub.docker.com/r/devilbox/php-fpm/)

---

## 7) Astuces & dépannage

- **Extensions “mysql_*”** : si votre application utilise encore `mysql_connect()` (legacy), assurez‑vous d’avoir activé le module `mysql` via `ENABLE_MODULES`. La base *php-fpm-5.4* inclut `mysqlnd` et *prod/mods* exposent les modules additionnels activables. [2](https://hub.docker.com/r/devilbox/php-fpm-5.4/)[1](https://hub.docker.com/r/devilbox/php-fpm/)
- **Droits de fichiers** : réglez `NEW_UID`/`NEW_GID` sur votre UID/GID locaux (souvent 1000) pour éviter les soucis de permissions en bind‑mount. [1](https://hub.docker.com/r/devilbox/php-fpm/)
- **ARM64 (Raspberry Pi/Apple Silicon)** : les images Devilbox annoncent le support **arm64** (en plus d’amd64), utile si vous build/runner sur ce type de machine. [1](https://hub.docker.com/r/devilbox/php-fpm/)

---

### Prêt à l’emploi — récap rapide

1. **Arborescence** :
   ```
   votre-projet/
   ├─ app/                # votre code (monté dans le conteneur)
   ├─ php-custom.d/       # vos *.ini (facultatif)
   ├─ Dockerfile          # (facultatif si vous prenez l'image telle-quelle)
   └─ docker-compose.yml
   ```

2. **Démarrer** :
   ```bash
   docker compose up -d
   ```

