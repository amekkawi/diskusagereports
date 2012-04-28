#!/bin/bash

SCRIPTDIR="$(cd "$(dirname "$0")"; pwd)"

SYNTAX() {
	echo "$0 [-kd] <directory-to-prepare> <version-name>"
}

KEEPDEV=0
if [ "$1" == "-kd" -o "$1" == "--keepdev" ]; then
	KEEPDEV=1
	shift
fi

if [ "$1" == "" -o "$2" == "" ]; then
	SYNTAX
	exit 1
elif [ ! -d "$1" ]; then
	echo "Directory specified does not exist."
	SYNTAX
	exit 1
fi

PREPAREDIR="$(cd "$1"; pwd)"
VER="$2"

[ -d "$PREPAREDIR/.git" ] && echo "Detected .git directory. This script cannot be run on a repo." && exit 1
[ ! -f "$PREPAREDIR/index.html" ] && echo "Could not find 'index.html'. May not be a Disk Usage Reports directory?" && exit 1
[ ! -d "$PREPAREDIR/\$dev" ] && echo "Could not find '\$dev' directory. May not be a Disk Usage Reports directory?" && exit 1
[ ! -d "$PREPAREDIR/js" ] && echo "Could not find 'js' directory. May not be a Disk Usage Reports directory?" && exit 1

echo "Editing index.html..."
sed -Ei '' -f "$SCRIPTDIR/inc/prepare.sed" "$PREPAREDIR/index.html"
[ "$?" != "0" ] && echo "FAILED" && exit 1

echo "Packing JavaScript..."
bash "$PREPAREDIR/\$dev/pack.sh"
[ "$?" != "0" ] && echo "FAILED" && exit 1

if [ "$KEEPDEV" == "0" ]; then
	echo "Removing \$dev..."
	rm -rf "$PREPAREDIR/\$dev"
	[ "$?" != "0" ] && echo "FAILED" && exit 1
fi

echo "Replacing \$Source Version\$ with tag name in scripts..."
find -E "$PREPAREDIR" -iregex '.+\.(php|js|css|html)$' -print0 | xargs -0 sed -Ei '' -e 's#\$Source Version\$#'"$VER"'#'
[ "$?" != "0" ] && echo "FAILED" && exit 1

echo "Replacing \$Source Version\$ with tag name in EXE..."
PADDEDTAG="$(printf '%-16s' "$VER")"
find -E "$PREPAREDIR" -iregex '.+\.(exe)$' -print0 | xargs -0 sed -Ei '' -e 's#\$Source Version\$#'"$PADDEDTAG"'#'
[ "$?" != "0" ] && echo "FAILED" && exit 1

echo "Removing .gitignore files..."
find "$PREPAREDIR" -name '.gitignore' -print0 | xargs -0 rm
