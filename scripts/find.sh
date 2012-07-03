#!/bin/bash

# Author: André Mekkawi
# Version: $Revision$
# License See LICENSE.txt or http://diskusagereports.com/license.html 
# Copyright: 2012 André Mekkawi <contact@andremekkawi.com>

SCRIPT_DIR="$(cd "$(dirname "$0")"; pwd)"
[ "$SCRIPT_DIR" == "/" ] && SCRIPT_DIR=""

# Set the timezone to UTC
export TZ=UTC

# Set the time locale to POSIX
export LC_ALL=C

function determine_format() {
	# Check if the find commands supports -printf and -mindepth
	line="$(find "$0" -mindepth 0 -printf "%y %TY-%Tm-%Td %TH:%TM:%TS %s %P\n" &> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | grep -Eq '^. [0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} [0-9]+ ' && format="find-printf" && formatarg="" && return 0
	
	# Make sure find supports -print0
	find "$0" -print0 &> /dev/null
	[ "$?" != "0" ] && return 50
	
	# Make sure find outputs results with the correct prefix.
	line="$(cd "$SCRIPT_DIR"; find . | head -n 2 | tail -n 1)"
	[ "${line:0:2}" != "./" ] && return 51
	
	# Make sure ls keeps the prefix.
	line="$(cd "$SCRIPT_DIR"; ls -d "./$(basename "$0")")"
	[ "${line:0:2}" != "./" ] && return 52
	
	# Check if FreeBSD -D <format> argument is available.
	# // TODO: Check if works on FreeBSD
	line="$(ls -ld -D '%Y-%m-%d %H:%M:%S' . 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | '{ print $6, substr($7, 0, 8), $8 }' | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} \.$' \
		&& awkarg='{ print substr($1, 0, 1), $6, substr($7, 0, 8), $5, substr($0, index($0, " " $7 " " $8) + length($7) + 4, 1024) }' \
		&& format="timestamp" && formatarg="-D '%Y-%m-%d %H:%M:%S'" && return 0
	
	# Check if --time-style is available.
	line="$(ls -ld --time-style='+%Y-%m-%d %H:%M:%S' . 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | awk '{ print $6, substr($7, 0, 8), $8 }' | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} \.$' \
		&& awkarg='{ print substr($1, 0, 1), $6, substr($7, 0, 8), $5, substr($0, index($0, " " $7 " " $8) + length($7) + 4, 1024) }' \
		&& format="timestamp" && formatarg="--time-style='+%Y-%m-%d %H:%M:%S'" && return 0
	
	# Check if --full-time is available.
	line="$(ls -ld --full-time . 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | awk '{ print $6, $7, $8, $9 }' | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]+ \+0000 \.$' \
		&& awkarg='{ print substr($1, 0, 1), $6, substr($7, 0, 8), $5, substr($0, index($0, " " $8 " " $9) + length($8) + 4, 1024) }' \
		&& format="timestamp" && formatarg="--full-time" && return 0
	
	# Check if -T for displaying full date/time is available.
	line="$(ls -ldT . 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | awk '{ print $6, $7, $8, $9, $10 }' | grep -Eq '^[A-Z][a-z]{2} [0-9]{1,2} [0-9]{2}:[0-9]{2}:[0-9]{2} [0-9]{4} \.$' \
		&& awkarg='BEGIN { split("Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec", month, " "); for (i=1; i<=12; i++) mdigit[month[i]] = sprintf("%02d", i) }; { print substr($1, 0, 1), $9 "-" mdigit[$6] "-" sprintf("%02d", $7), $8, $5, substr($0, index($0, " " $8 " " $9 " ") + 17, 1024) }' \
		&& format="timestamp" && formatarg="-T" && return 0
	
	# // TODO: Possibly allow the default format since we include current date/time? Will not be able to determine time however.
	
	return 1001
}

function determine_nosort() {
	[ "$format" == "find-printf" ] && nosortarg="" && return 0

	# Check for -U version of 'no sort'.
	line="$(ls -ldU .. . 2> /dev/null)"
	[ "$?" == "0" -a "$(echo "$line" | awk '{ print $9 }' | tr '\n' ' ' | awk '{ print $1, $2 }')" == ".. ." ] && nosortarg='-U' && return 0
	
	# Check for -f version of 'no sort'.
	# This check must be after -U since -f can turn off -l on some systems.
	line="$(ls -ldf .. . 2> /dev/null)"
	[ "$?" == "0" -a "$(echo "$line" | awk '{ print $9 }' | tr '\n' ' ' | awk '{ print $1, $2 }')" == ".. ." ] && nosortarg='-f' && return 0
	
	return 1002
}

function syntax() {
	[ "$*" != "" ] && echo "$*" 1>&2
	echo "Syntax: $0 [OPTIONS] <directory-to-list>" 1>&2
	echo "Use -h for full help or visit diskusagereports.com/docs." 1>&2
	exit 1
}

function syntax_long() {
	echo "Syntax: $0 <directory-to-list>

<directory-to-scan>
The directory that the list of sub-directories and files will be created for.

See also: diskusagereports.com/docs
"
	exit 1
}

# Parse arguments.
while (( "$#" )); do
	if [ "$1" == '-h' -o "$1" == '-?' -o "$1" == '--help' ]; then
		syntax_long
	
	else
		[ "$real" != "" ] && syntax "Argument not expected: $1"
		[ ! -d "$1" ] && syntax "<directory-to-list> does not exist or is not a directory: $real" 1>&2
		real=$(cd "$1" && pwd)
	fi
	
	shift
done

# Make sure the <directory-to-list> has been set.
[ "$real" == "" ] && syntax "The <directory-to-list> argument is missing." 1>&2

# Make sure the <directory-to-list> is a directory.
[ ! -d "$real" ] && syntax "The <directory-to-list> is not a directory." 1>&2

# Split the <directory-to-list>
dir=$(dirname "$real")
base=$(basename "$real")

# Set the dir to an empty string if we are scanning '/'.
if [ "$dir$base" == '//' ]; then
	dir=
	base='/'
fi

# Determine the output format/method
determine_format
ret="$?"
[ "$ret" != "0" ] && echo "The commands on this system do not support the features necessary to use this script (error $ret). Please use scripts/find.php instead." && exit $ret

determine_nosort
ret="$?"
[ "$ret" != "0" ] && echo "The commands on this system do not support the features necessary to use this script (error $ret). Please use scripts/find.php instead." && exit $ret

timestamp="$(date '+%Y-%m-%d %H:%M:%S')"
echo "## v2 / ${timestamp:0:19} $format $(echo "$dir" | sed -e 's/\\/\\\\/g' -e 's/ /\\ /g') $(echo "$base" | sed -e 's/\\/\\\\/g' -e 's/ /\\ /g')"

if [ "$format" == "find-printf" ]; then
	find "$real" -mindepth 1 -printf "%y %TY-%Tm-%Td %TH:%TM:%TS %s %P\n"
	
else
	cd "$real"
	find . -print0 | eval xargs -0 ls -ld $formatarg $nosortarg | tail -n +2 | awk "$awkarg"
fi

exit 0