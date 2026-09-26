#!/usr/bin/env bash
#
# Déploiement en production : récupère la dernière version depuis GitHub
# et exécute les commandes nécessaires au bon fonctionnement du site.
#
# Usage :
#   ./deploy.sh                 # déploie la branche "production"
#   BRANCH=autre ./deploy.sh    # déploie une autre branche
#
# La branche "production" est générée par la GitHub Action build-production :
# elle contient le code de main et les assets déjà compilés (public/build).
#   PHP=/usr/bin/php8.3 COMPOSER="php8.3 /usr/local/bin/composer" ./deploy.sh
#

set -Eeuo pipefail

BRANCH="${BRANCH:-production}"
REMOTE="${REMOTE:-origin}"
PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"

cd "$(dirname "$(readlink -f "$0")")"

step() {
    printf '\n\033[1;34m==> %s\033[0m\n' "$1"
}

on_error() {
    printf '\n\033[1;31m!! Échec du déploiement (ligne %s). Le site reste en maintenance.\033[0m\n' "$1" >&2
    printf '   Corrigez le problème puis relancez le script, ou exécutez "%s artisan up".\n' "$PHP" >&2
}
trap 'on_error $LINENO' ERR

if [ ! -f .env ]; then
    echo "Fichier .env introuvable : copiez .env.example en .env et configurez-le avant de déployer." >&2
    exit 1
fi

step "Passage en mode maintenance"
$PHP artisan down --retry=60 || true

step "Récupération de la dernière version ($REMOTE/$BRANCH)"
git fetch "$REMOTE" "$BRANCH"
git reset --hard "$REMOTE/$BRANCH"

step "Installation des dépendances PHP"
$COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ ! -f public/build/manifest.json ]; then
    echo "public/build/manifest.json introuvable : la branche $BRANCH ne contient pas les assets compilés. Le site reste en maintenance." >&2
    exit 1
fi

step "Migrations de la base de données"
$PHP artisan migrate --force

step "Lien de stockage public"
if [ ! -e public/storage ]; then
    $PHP artisan storage:link
fi

step "Mise en cache de la configuration, des routes, des vues et de Filament"
$PHP artisan optimize:clear
$PHP artisan optimize
$PHP artisan filament:optimize

step "Redémarrage des workers de file d'attente"
$PHP artisan queue:restart

step "Sortie du mode maintenance"
$PHP artisan up

printf '\n\033[1;32m✔ Déploiement terminé (%s).\033[0m\n' "$(git log -1 --pretty='%h - %s')"
