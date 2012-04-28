#!/bin/bash

TAG="$1"
SCRIPTDIR="$(cd "$(dirname "$0")"; pwd)"
REPODIR="$(dirname "$SCRIPTDIR")"

[ "$TAG" == "" ] && echo "Syntax: $0 <tag-name>" && exit 1

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

bash "$SCRIPTDIR/preparerelease.sh" "$PREPAREDIR"

echo "Compressing to $SCRIPTDIR/local/diskusagereports_$TAG.zip..."
cd "$TMPDIR"
zip -qr - "diskusagereports_$TAG" > "$SCRIPTDIR/local/diskusagereports_$TAG.zip"
[ "$?" != "0" ] && echo "FAILED" && exit 1

echo "Removing tmp dir..."
rm -rf "$TMPDIR"
[ "$?" != "0" ] && echo "FAILED" && exit 1
