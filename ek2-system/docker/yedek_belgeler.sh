#!/bin/bash
# EK-2 fotoğraflarının (tek yer: /srv/ek2/belgeler) gece yedeği — artımlı, silinen dosya yedekte kalır. Cron 03:50.
set -euo pipefail
H=/root/yedekler/ek2-belgeler; mkdir -p "$H"; chmod 700 "$H"
rsync -a /srv/ek2/belgeler/ "$H/"
echo "$(date '+%F %T') ek2 belge yedeği tamam: $(find "$H" -type f | wc -l) dosya, $(du -sh "$H" | cut -f1)"
