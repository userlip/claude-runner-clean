#!/bin/sh
set -eu

cd "$(dirname "$0")"

for lockfile in */package-lock.json; do
    [ -f "$lockfile" ] || continue
    directory=${lockfile%/package-lock.json}
    npm ci --prefix "$directory" --include=dev --no-audit --no-fund
    npm run build --prefix "$directory"
done
