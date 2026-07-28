#!/bin/sh

vitals_dir=$(readlink -f "$0" | xargs dirname)

mkdir -p "$HOME/host/caddy/data/data/vitals"
cp -r "$vitals_dir"/public/* "$HOME/host/caddy/data/data/vitals/"
