#!/bin/sh

vitals_dir=$(readlink -f "$0" | xargs dirname)

mkdir -p "$HOME/host/caddy/data/data/vitals"
cp "$vitals_dir/public/app.js"      "$HOME/host/caddy/data/data/vitals/"
cp "$vitals_dir/public/chart-v4.js" "$HOME/host/caddy/data/data/vitals/"
cp "$vitals_dir/public/index.html"  "$HOME/host/caddy/data/data/vitals/"
