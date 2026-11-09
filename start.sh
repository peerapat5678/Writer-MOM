#!/bin/sh
# MOM Writer launcher for macOS / Linux (needs python3)
cd "$(dirname "$0")" || exit 1
URL="http://localhost:8765/"
( sleep 1; (command -v open >/dev/null && open "$URL") || (command -v xdg-open >/dev/null && xdg-open "$URL") ) &
echo "MOM Writer is running at $URL  (Ctrl+C to stop)"
python3 -m http.server 8765 --bind 127.0.0.1
