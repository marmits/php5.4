# PHP5.4


### devilbox - PHP 5.4 + Apache 2.4

- Version avec 2 containers devilbox/php-fpm:5.4-prod et httpd:2.4:  
  [PHP54.md](PHP54.md)

### INSTALL
`./run.sh`

### USAGE

- http://legacy54:8004/
- http://projet154:8004/
- http://projet254:8004/

### COMMANDES

`docker compose exec web php -v`
`docker compose exec web php -m | sort`


### ALTERNATIVE
- voir aussi Version avec seulement 1 container PHP 5.4 + FPM, Apache sur l'hôte:  
  [README_V1.md](README_V1.md)