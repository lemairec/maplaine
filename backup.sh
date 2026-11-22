#!/bin/bash
# Export de la base + uploads sur le serveur OVH, puis import en local
set -e

ssh maplainemk@ssh.cluster023.hosting.ovh.net 'cd ~/maplaine && sh ~/maplaine/export.sh'
sh ~/workspace/maplaine/import.sh
