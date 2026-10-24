#!/usr/bin/env bash
# Atomic release of PlainShow Blog.
#
#   ops/deploy.sh [ref]          build ref (default: HEAD's tag or commit) and switch to it
#   ops/deploy.sh --rollback     switch back to the previous release
#
# Releases live in /srv/plainshow/blog/releases/<name>. config/, sessions and
# thumbnails come from shared/; uploads (films, profile pictures, the sand
# games) stay in an uploads folder and are linked in: the original blog folder
# on the first PlainShow, platform system `blog_uploads` elsewhere. The blog
# runs as the web server's user (www-data on Debian), so that user reads the
# code and config.
set -euo pipefail

BASE=/srv/plainshow/blog
SOURCE=$(cd "$(dirname "$0")/.." && pwd)
KEEP=3
say() { printf '\033[1m%s\033[0m\n' "$*"; }

# Facts about this server from the platform file (/etc/plainshow/platform.json).
platform() { php -r '$d = json_decode((string) @file_get_contents("/etc/plainshow/platform.json"), true); echo $d["system"][$argv[1]] ?? "";' "$1"; }
WEB_SERVICE=$(platform web_service); WEB_SERVICE=${WEB_SERVICE:-apache2}
WEB_USER=$(platform web_user); WEB_USER=${WEB_USER:-www-data}
WEB_GROUP=$(id -gn "$WEB_USER")
PHP_FPM=$(platform php_fpm_service)
UPLOADS=$(platform blog_uploads); UPLOADS=${UPLOADS:-/var/www/html/projects/hugohansson/woc/public}
reload() {
    sudo -n systemctl reload "$WEB_SERVICE" || echo "Reload $WEB_SERVICE to finish switching." >&2
    if [ -n "$PHP_FPM" ]; then sudo -n systemctl reload "$PHP_FPM" || echo "Reload $PHP_FPM to finish switching." >&2; fi
}

if [ "${1:-}" = "--rollback" ]; then
    current=$(readlink "$BASE/current")
    previous=$(ls -1dt "$BASE"/releases/* | grep -vx "$current" | head -1)
    [ -n "$previous" ] || { echo "No earlier release to roll back to." >&2; exit 1; }
    ln -sfn "$previous" "$BASE/current.new" && mv -Tf "$BASE/current.new" "$BASE/current"
    reload
    say "Rolled back to $(basename "$previous")"
    exit 0
fi

cd "$SOURCE"
if [ "${1:-}" != --worktree ]; then
    [ -z "$(git status --porcelain --untracked-files=no)" ] || { echo "Commit changes in $SOURCE first." >&2; exit 1; }
fi
REF=${1:-$(git describe --tags --exact-match 2>/dev/null || git rev-parse --short HEAD)}
NAME="$(date +%Y%m%d%H%M%S)-${REF//\//-}"
RELEASE="$BASE/releases/$NAME"

say "Building blog $REF into $RELEASE"
mkdir -p "$RELEASE"
git archive --format=tar "${REF/--worktree/HEAD}" | tar -x -C "$RELEASE"
if [ "$REF" = --worktree ]; then
    git diff --binary HEAD | (cd "$RELEASE" && git apply)
    git ls-files --others --exclude-standard -z | tar --null -T - -cf - | tar -x -C "$RELEASE"
fi
rm -rf "$RELEASE/config" "$RELEASE/app/sessions" "$RELEASE/app/cache" "$RELEASE/app/storage"
ln -s "$BASE/shared/config" "$RELEASE/config"
ln -s "$BASE/shared/app/sessions" "$RELEASE/app/sessions"
ln -s "$BASE/shared/app/cache" "$RELEASE/app/cache"
ln -s "$BASE/shared/app/storage" "$RELEASE/app/storage"
rm -rf "$RELEASE/public/assets/vids" "$RELEASE/public/assets/profile_pictures" "$RELEASE/public/sand"
ln -s "$UPLOADS/assets/vids" "$RELEASE/public/assets/vids"
ln -s "$UPLOADS/assets/profile_pictures" "$RELEASE/public/assets/profile_pictures"
ln -s "$UPLOADS/sand" "$RELEASE/public/sand"

cd "$RELEASE"
GIT_TERMINAL_PROMPT=0 COMPOSER_NO_INTERACTION=1 composer install --no-dev --optimize-autoloader --no-progress --quiet --ignore-platform-req=php
php app/console.php migrate

ME=$(id -un)
sudo -n chown -R "$ME:$WEB_GROUP" "$RELEASE"
sudo -n chmod -R g+rX,o-rwx "$RELEASE"
sudo -n find "$BASE/shared/app" -type d -exec chmod 2770 {} +
sudo -n chmod 0640 "$BASE"/shared/config/*

ln -sfn "$RELEASE" "$BASE/current.new"
mv -Tf "$BASE/current.new" "$BASE/current"
reload
say "blog is now $NAME"

ls -1dt "$BASE"/releases/* | tail -n +$((KEEP + 1)) | xargs -r rm -rf
