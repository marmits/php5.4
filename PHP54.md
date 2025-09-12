**Intégration clé en main** avec deux services DOCKER :

- `php54-fpm`
- `apache` (basé sur `httpd:2.4`), qui écoute en 8004 dans le conteneur et est publié en `127.0.0.1:8004` côté hôte

---

# PHP 5.4 + FPM avec Devilbox (Docker) + httpd:2.4

> **Pourquoi monter le code dans les deux conteneurs ?**  
> Apache doit **servir les fichiers statiques** (CSS/JS/images), et PHP‑FPM doit **lire les scripts**. On garde **les mêmes chemins internes** `/var/www/...` dans **les deux** conteneurs pour que `ProxyPassMatch` fonctionne sans surprises.

---

## 3) Config Apache

### `apache/httpd.conf`

Un `httpd.conf` minimaliste, qui **charge les modules nécessaires** (proxy, proxy_fcgi, rewrite…) et inclut tes vhosts :

`/etc/hosts`
```
127.0.0.1 legacy54
127.0.0.1 projet154
127.0.0.1 projet254
```
---

## 4) Démarrage & tests

1) (Évite le conflit de port) **Apache sur WSL**
   vérifier les ports OQP
   sudo ss -ltnp | grep ':8004 '

2) **Lance les conteneurs** :
   ```bash
   docker compose up -d
   ```

3) **Vérifie les logs** :
   ```bash
   docker compose logs -f apache
   docker compose logs -f php54-fpm
   ```

4) **Teste depuis l’hôte** :
    - http://legacy54:8004/
    - http://projet154:8004/
    - http://projet254:8004/


## 6) Petits tips “legacy PHP 5.4”

- Si tu as des URLs du type `/script.php/extra/path`, on l’a déjà couvert avec `ProxyPassMatch "^/(.*\.php(/.*)?)$"`.
- Si certaines applis exigent `AcceptPathInfo On`, tu peux l’ajouter dans le `<Directory>` du vhost concerné.
- Si tu vois des 403/404 uniquement sur les **assets statiques**, c’est souvent un souci d’`AllowOverride` ou de chemin : vérifie que **Apache** voit bien les mêmes chemins que **PHP‑FPM** (`/var/www/...`) et que `.htaccess` est autorisé.

---
