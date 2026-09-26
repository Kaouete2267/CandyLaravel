#!/usr/bin/env bash
#
# Déploiement en production : récupère la dernière version depuis GitHub
# et exécute les commandes nécessaires au bon fonctionnement du site.
#
# Usage :
#   ./deploy.sh                 # déploie la branche "production"
#   BRANCH=autre ./deploy.sh    # déploie une autre branche
#   PHP=/usr/local/php8.3/bin/php ./deploy.sh
#   COMPOSER_BIN="php $HOME/composer.phar" ./deploy.sh
#
# La branche "production" est générée par la GitHub Action build-production :
# elle contient le code de main et les assets déjà compilés (public/build).
#

set -Eeuo pipefail

BRANCH="${BRANCH:-production}"
REMOTE="${REMOTE:-origin}"

# Binaire PHP. Sur OVH mutualisé, "php" doit pointer sur PHP 8.3+ (PATH dans ~/.bashrc),
# sinon indiquer le chemin complet (ex. /usr/local/php8.3/bin/php).
PHP="${PHP:-php}"

# Commande Composer : par défaut composer.phar à la racine du projet, lancé avec $PHP.
# Pas de variable COMPOSER : Composer la lit lui-même comme nom du fichier composer.json.
COMPOSER_BIN="${COMPOSER_BIN:-$PHP composer.phar}"
# COMPOSER_BIN peut contenir plusieurs mots : on le découpe en tableau pour l'appeler correctement.
read -r -a COMPOSER_CMD <<< "$COMPOSER_BIN"

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

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1 || ! git remote get-url "$REMOTE" >/dev/null 2>&1; then
    echo "Ce dossier n'est pas un dépôt git relié à GitHub (remote \"$REMOTE\") : initialisez-le avant de déployer." >&2
    exit 1
fi

step "Passage en mode maintenance"
$PHP artisan down --retry=60 || true

step "Récupération de la dernière version ($REMOTE/$BRANCH)"
git fetch "$REMOTE" "$BRANCH"
git reset --hard "$REMOTE/$BRANCH"

step "Installation des dépendances PHP"
"${COMPOSER_CMD[@]}" install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ ! -f public/build/manifest.json ]; then
    echo "public/build/manifest.json introuvable : la branche $BRANCH ne contient pas les assets compilés. Le site reste en maintenance." >&2
    exit 1
fi

step "Migrations de la base de données"
$PHP artisan migrate --force

step "Lien de stockage public"
# Lien relatif : un lien absolu (/home/…) ne serait pas valide pour Apache (voir plus bas).
if [ ! -e public/storage ]; then
    $PHP artisan storage:link --relative
fi

# Pas de "artisan optimize" : chez OVH, le projet n'a pas le même chemin en SSH (/home/…) que pour Apache
# (/homez.…/…). Les caches de configuration, de routes, de vues et d'icônes Filament enregistrent des chemins
# absolus : générés ici, ils font planter le site (erreur 500 sans aucun log). Seul le cache des événements
# n'en contient pas.
step "Vidage des caches et mise en cache des événements"
$PHP artisan optimize:clear
$PHP artisan event:cache

step "Redémarrage des workers de file d'attente"
$PHP artisan queue:restart

step "Sortie du mode maintenance"
$PHP artisan up

printf '\n\033[1;32m✔ Déploiement terminé (%s).\033[0m\n' "$(git log -1 --pretty='%h - %s')"
