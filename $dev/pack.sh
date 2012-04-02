#!/bin/bash

DEV=$(cd "$(dirname "$0")"; pwd)
CODEBASE=$(dirname "$DEV")
SED='s/^.*<script .+ src="([^"]+)".*$/\1/p'

cd "$CODEBASE"

echo "
// Packed version of .js scripts in index.html
// See original files for copyright info.
" > "js/packed.js"

while IFS="" read file; do
	
	[ ! -f "$file" ] && echo "File not found: $file" && exit 1
	
	echo "// Packed $file" >> "js/packed.js"
	cat "$file" | php "$DEV/inc/packer-stdin.php" >> "js/packed.js"
	
	echo >> "js/packed.js"
	echo >> "js/packed.js"
done < <(sed -nE -e "$SED" "index.html" | grep -v 'packed.js')

packed=$(cat "js/packed.js" | wc -c | tr -d ' ')
	
unpacked=$(sed -nE -e "$SED" "index.html" \
	| grep -v 'packed.js' | xargs cat | wc -c | tr -d ' ')

percent=$(echo "scale=2; $packed * 100 / $unpacked" | bc)

echo "$unpacked to $packed ("$percent"%)"