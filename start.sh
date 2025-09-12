#!/usr/bin/env bash
set -euo pipefail

export NEW_UID="$(id -u)"
export NEW_GID="$(id -g)"

docker compose down
docker compose up -d --force-recreate

cat .env