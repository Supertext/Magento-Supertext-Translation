#!/usr/bin/env bash
# Builds dist/magento-supertext-translation-<version>.zip: the module's files, to unpack into
# app/code/Supertext/Translation. The version comes from composer.json.
set -euo pipefail
cd "$(dirname "$0")"

version=$(php -r 'echo json_decode(file_get_contents("composer.json"), true)["version"] ?? "";')
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "No X.Y.Z version in composer.json" >&2; exit 1; }

zip="dist/magento-supertext-translation-$version.zip"
mkdir -p dist
rm -f "$zip"
zip -qr "$zip" Api Block Console Controller Model etc view composer.json registration.php LICENSE README.md CHANGELOG.md \
  -x '*.DS_Store'
echo "$zip"
