#!/bin/bash

TMPDIR="$(mktemp -d /tmp/diskusage-mirror-to-sf.XXXXXX)"
echo $TMPDIR

git clone --mirror git://github.com/amekkawi/diskusagereports.git "$TMPDIR"

cd "$TMPDIR"
git push -v --mirror ssh://amekkawi@diskusagereport.git.sourceforge.net/gitroot/diskusagereport/diskusagereport

rm -rf "$TMPDIR"