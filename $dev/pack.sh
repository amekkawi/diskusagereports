#!/bin/bash

DEV=$(cd "$(dirname "$0")"; pwd)
CODEBASE=$(dirname "$DEV")

cd "$CODEBASE"

sed -nE -e 's/^.*<script .+ src="([^"]+)".*$/\1/p' "$CODEBASE/index.html" \
	| grep -v 'packed.js' | grep -v '.min.js' \
	| xargs cat \
	| php "$DEV/packer-stdin.php" \
	> "$CODEBASE/js/packed.js"

packed=$(cat "$CODEBASE/js/packed.js" | wc -c | tr -d ' ')
	
unpacked=$(sed -nE -e 's/^.*<script .+ src="([^"]+)".*$/\1/p' "$CODEBASE/index.html" \
	| grep -v 'packed.js' | grep -v '.min.js' \
	| xargs cat | wc -c | tr -d ' ')

percent=$(echo "scale=2; $packed * 100 / $unpacked" | bc)

echo "$unpacked to $packed ("$percent"%)"