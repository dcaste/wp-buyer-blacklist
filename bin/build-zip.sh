#!/usr/bin/env bash
# Builds dist/wp-buyer-blacklist-<version>.zip from the working tree.
# The ZIP holds the plugin files only: no sources, tests, docs or vendor/.
set -euo pipefail

cd "$(dirname "$0")/.."

slug="wp-buyer-blacklist"
version="$(sed -n 's/^ \* Version: *//p' "${slug}.php")"
if [ -z "$version" ]; then
	echo "Could not read the version from ${slug}.php" >&2
	exit 1
fi

# The admin app in build/ is committed, so make sure it is current.
if [ -n "$(git status --porcelain build assets/admin 2>/dev/null)" ]; then
	echo "build/ or assets/admin/ has uncommitted changes. Run npm run build and commit first." >&2
	exit 1
fi

rm -rf dist
mkdir -p "dist/${slug}"
rsync -a --exclude-from=.distignore ./ "dist/${slug}/"
(cd dist && zip -qr "${slug}-${version}.zip" "${slug}")

echo "Built dist/${slug}-${version}.zip"
