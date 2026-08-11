#!/usr/bin/env bash
#
# DEPRECATED: manual deployment helper for the old split setup where a
# host-level Caddy served the static assets and a PHP dev server only
# handled /api/*.
#
# The Docker setup will make this obsolete — FrankenPHP will serve the
# whole app (assets + API) from one container, so there is nothing to
# copy. This script is kept for reference until `main` runs in Docker.

set -euo pipefail

vitals_dir=$(readlink -f "$0" | xargs dirname)

mkdir -p "$HOME/host/caddy/data/data/vitals"
cp -r "$vitals_dir"/public/* "$HOME/host/caddy/data/data/vitals/"
