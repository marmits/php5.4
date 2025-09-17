FROM mariadb:10.3

ENV LANG=C.UTF-8 \
    TZ=Europe/Paris

HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD mariadb-admin ping -uroot -p"$MARIADB_ROOT_PASSWORD" || exit 1
