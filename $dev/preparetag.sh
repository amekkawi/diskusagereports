#!/bin/bash

TAG="$1"
SCRIPTDIR="$(cd "$(dirname "$0")"; pwd)"
REPODIR="$(dirname "$SCRIPTDIR")"

[ "$(git tag -l "$TAG")" != "$TAG" ] && \
	echo "Tag '$TAG' was not found." && exit 1

TMPDIR="$(mktemp -d "$SCRIPTDIR/local/prepare_$TAG.XXXXXXXX")"
[ ! -d "$TMPDIR" ] && echo "Failed to create prepare directory at: $TMPDIR" && exit 1

PREPAREDIR="$TMPDIR/diskusagereports_$TAG"
mkdir "$PREPAREDIR"
[ "$?" != "0" ] && echo "Failed to create $PREPAREDIR" && exit 1

echo "Exporting tag files to: $PREPAREDIR..."

cd "$REPODIR"
git archive --format=tar "$TAG" | tar x -C "$PREPAREDIR"
[ "${PIPESTATUS[0]} $?" != "0 0" ] && echo "FAILED" && exit 1

echo "Editing index.html..."
sed -Ei '' -f "$SCRIPTDIR/inc/prepare.sed" "$PREPAREDIR/index.html"
[ "$?" != "0" ] && echo "FAILED" && exit 1

echo "Packing JavaScript..."
bash "$PREPAREDIR/\$dev/pack.sh"
[ "$?" != "0" ] && echo "FAILED" && exit 1

echo "Removing \$dev..."
rm -rf "$PREPAREDIR/\$dev"
[ "$?" != "0" ] && echo "FAILED" && exit 1

echo "Replacing \$Source Version\$ with tag name in scripts..."
find -E "$PREPAREDIR" -iregex '.+\.(php|js|css|html)$' -print0 | xargs -0 sed -Ei '' -e 's#\$Source Version\$#'"$TAG"'#'
[ "$?" != "0" ] && echo "FAILED" && exit 1

echo "Replacing \$Source Version\$ with tag name in EXE..."
PADDEDTAG="$(printf '%-16s' "$TAG")"
find -E "$PREPAREDIR" -iregex '.+\.(exe)$' -print0 | xargs -0 sed -Ei '' -e 's#\$Source Version\$#'"$PADDEDTAG"'#'
[ "$?" != "0" ] && echo "FAILED" && exit 1

echo "Removing .gitignore files..."
find "$PREPAREDIR" -name '.gitignore' -print0 | xargs -0 rm

echo "Compressing to $SCRIPTDIR/local/diskusagereports_$TAG.zip..."
cd "$TMPDIR"
zip -qr - "diskusagereports_$TAG" > "$SCRIPTDIR/local/diskusagereports_$TAG.zip"
[ "$?" != "0" ] && echo "FAILED" && exit 1

echo "Removing tmp dir..."
rm -rf "$TMPDIR"
[ "$?" != "0" ] && echo "FAILED" && exit 1
