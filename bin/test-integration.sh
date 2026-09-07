#!/usr/bin/env bash
set -euo pipefail

plugin_dir="$(cd "$(dirname "$0")/.." && pwd)"
export WP_TESTS_DIR="${WP_TESTS_DIR:-${TMPDIR:-/tmp}/wordpress-tests-lib}"
export WC_PLUGIN_DIR="${WC_PLUGIN_DIR:-$(dirname "$plugin_dir")/woocommerce}"

exec vendor/bin/phpunit --configuration phpunit.integration.xml.dist
