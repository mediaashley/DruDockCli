#!/bin/bash

BLUE='\033[0;34m'
GREEN='\033[0;32m'
NC='\033[0m'

cat <<EOF

  _  _        _    _ _    ____  _       _ _        _
 | || |      / \  | | |  |  _ \(_) __ _(_) |_ __ _| |
 | || |_    / _ \ | | |  | | | | |/ _  | | __/ _  | |
 |__   _|  / ___ \| | |  | |_| | | (_| | | || (_| | |
    |_|   /_/   \_\_|_|  |____/|_|\__, |_|\__\__,_|_|
                                  |___/

EOF

echo -e "${GREEN}DEMO TEST${NC}"

./bin/drudock about

./bin/drudock --version

./bin/drudock env:init travisapp --type D8 --dist Basic --appsrc New --apphost drudock.dev && \
cd travisapp && \
../bin/drudock build:init
