#!/bin/bash

TAG="$1"
SCRIPTDIR="$(cd "$(dirname "$0")"; pwd)"

[ "$TAG" == "" ] && echo "Syntax: $0 <tagname>" && exit 1

if [ -d "$SCRIPTDIR/report" ]; then
	rm -rf "$SCRIPTDIR/report"
	[ "$?" != "0" ] && echo "Failed to remove existing report dir." && exit 1
fi

mkdir "$SCRIPTDIR/report"
[ "$?" != "0" ] && echo "Failed to create report dir." && exit 1

echo "Downloading and Extracting Tag..."
curl -sfL "https://github.com/amekkawi/diskusagereports/tarball/$1" | 
	tar -xz -C "$SCRIPTDIR/report" --exclude '*/.git*' --strip-components 1 -
	# --exclude '*/scripts'

ret="${PIPESTATUS[0]} ${PIPESTATUS[1]}"
ret1="${ret%% *}"
ret2="${ret##* }"

[ "$ret1" != "0" ] && echo "Failed to download tarball. Tag may not exist." && exit 1
[ "$ret2" != "0" ] && echo "Failed to extract tarball. May be an invalid tarball." && exit 1

bash "$SCRIPTDIR/report/\$dev/preparerelease.sh" --keepdev "$SCRIPTDIR/report" "$TAG"

echo "Removing \$dev..."
rm -rf "$SCRIPTDIR/report/\$dev"
[ "$?" != "0" ] && echo "FAILED" && exit 1

#mkdir "$SCRIPTDIR/reportraw/\$dev/local"
#[ "$?" != "0" ] && echo "Failed to create local dir." && exit 1

#bash "$SCRIPTDIR/reportraw/\$dev/preparetag.sh" "$TAG"

echo "Creating report..."
php "$SCRIPTDIR/report/scripts/find.php" /Developer/Applications/Utilities/ | php "$SCRIPTDIR/report/scripts/process.php" -n "Applications/Utilities from Xcode" "$SCRIPTDIR/report/data/demo"
[ "$?" != "0" ] && echo "Failed to create report." && exit 1

rm -rf "$SCRIPTDIR/report/scripts"
[ "$?" != "0" ] && echo "Failed to remove scripts directory." && exit 1