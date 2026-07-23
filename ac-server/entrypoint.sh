#!/bin/sh
set -e

if [ ! -f cfg/server_cfg.ini ]; then
    echo "entrypoint: cfg/server_cfg.ini not found — mount the cfg/ directory as a volume" >&2
    exit 1
fi

exec ./acServer
