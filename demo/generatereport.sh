#!/bin/bash

TAG="$1"
SCRIPTDIR="$(cd "$(dirname "$0")"; pwd)"

[ "$TAG" == "" ] && echo "Syntax: $0 <tagname>" && exit 1

rm -rf "$SCRIPTDIR/report"
[ "$?" != "0" ] && echo "Failed to remove old report dir." && exit 1

mkdir "$SCRIPTDIR/report"
[ "$?" != "0" ] && echo "Failed to create new report dir." && exit 1

echo "Downloading and Extracting Tag..."
curl -sfL "https://github.com/amekkawi/diskusagereports/tarball/$1" | 
	tar -xz -C "$SCRIPTDIR/report" --exclude '*/.git*' --exclude '*/$dev' --strip-components 1 -
	# --exclude '*/scripts'

ret="${PIPESTATUS[0]} ${PIPESTATUS[1]}"
ret1="${ret%% *}"
ret2="${ret##* }"

[ "$ret1" != "0" ] && echo "Failed to download tarball. Tag may not exist." && exit 1
[ "$ret2" != "0" ] && echo "Failed to extract tarball. May be an invalid tarball." && exit 1

echo "Creating report..."
php "$SCRIPTDIR/report/scripts/find.php" /Developer/Applications/Utilities/ | php "$SCRIPTDIR/report/scripts/process.php" -n "Applications/Utilities from Xcode" "$SCRIPTDIR/report/data/demo"
[ "$?" != "0" ] && echo "Failed to create report." && exit 1

rm -rf "$SCRIPTDIR/report/scripts"
[ "$?" != "0" ] && echo "Failed to remove scripts directory." && exit 1