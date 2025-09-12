#!/usr/bin/env bash
# start.sh — charge .env puis .env.local, exporte NEW_UID/GID, lance docker compose
set -euo pipefail

# --- Répertoire racine du projet ---
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

ENV_FILE="$ROOT_DIR/.env"
ENV_LOCAL_FILE="$ROOT_DIR/.env.local"

# --- Détection de Docker Compose (v2 'docker compose' ou v1 'docker-compose') ---
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  COMPOSE_CMD=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE_CMD=(docker-compose)
else
  echo "❌ Docker Compose introuvable. Installe 'docker' (v2) ou 'docker-compose' (v1)." >&2
  exit 1
fi

# --- Chargement .env puis .env.local (le second surcharge le premier) ---
# ATTENTION : on 'source' les fichiers. Assure-toi que les lignes sont de la forme KEY=VALUE (sans espaces autour de '=')
set -a
[ -f "$ENV_FILE" ] && . "$ENV_FILE"
[ -f "$ENV_LOCAL_FILE" ] && . "$ENV_LOCAL_FILE"
set +a

# --- UID/GID dynamiques (surchargent tout le reste, sans écrire dans .env) ---
export NEW_UID="$(id -u)"
export NEW_GID="$(id -g)"

# --- (Optionnel) Nettoyage des doublons dans .env pour NEW_UID/NEW_GID (décommenter pour l'activer) ---
# if [ -f "$ENV_FILE" ]; then
#   awk -F= '
#     BEGIN { OFS="=" }
#     { lines[NR]=$0; key=$1; if(key=="NEW_UID" || key=="NEW_GID"){last[key]=NR} }
#     END {
#       for (i=1;i<=NR;i++) {
#         split(lines[i],a,"=");
#         k=a[1];
#         if ((k=="NEW_UID" || k=="NEW_GID") && i!=last[k]) continue; # garde la dernière occurrence
#         print lines[i]
#       }
#     }' "$ENV_FILE" > "$ENV_FILE.tmp" && mv "$ENV_FILE.tmp" "$ENV_FILE"
# fi

# --- Récapitulatif utile ---
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📄  Envs chargés :"
[ -f "$ENV_FILE" ]       && echo "   - $ENV_FILE"
[ -f "$ENV_LOCAL_FILE" ] && echo "   - $ENV_LOCAL_FILE (prioritaire)"
echo "👤  UID:GID           : ${NEW_UID}:${NEW_GID}"
echo "🗄️   DB (si définie)   : ${DB_USER:-?}@${DB_HOST:-?}:${DB_PORT:-?}/${DB_NAME:-?}"
echo "🔧 Compose            : ${COMPOSE_CMD[*]}"
echo "📂 Dossier projet     : $ROOT_DIR"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# --- Validation de la configuration Compose résolue ---
"${COMPOSE_CMD[@]}" config >/dev/null

# --- Redémarrage propre ---
"${COMPOSE_CMD[@]}" down
"${COMPOSE_CMD[@]}" up -d --force-recreate --remove-orphans

echo "✅ Stack démarrée."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
"${COMPOSE_CMD[@]}" ps
