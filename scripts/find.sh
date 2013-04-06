#!/bin/bash

# Author: André Mekkawi
# Version: $Revision$
# License See LICENSE.txt or http://diskusagereports.com/license.html 
# Copyright: 2012 André Mekkawi <contact@andremekkawi.com>

# Set the timezone to UTC
export TZ=UTC

# Set the time locale to POSIX
export LC_ALL=C

# Get bash's dirname for format checks.
[ ! -f "$(command -v bash 2> /dev/null)" ] && echo "ERROR: Unable to determine the path of bash." && exit 2
BASH_DIR="$(cd "$(dirname "$(command -v bash)")"; pwd)"
[ ! -f "$BASH_DIR/bash" ] && echo "ERROR: Unable to determine the dirname of bash." && exit 2

function determine_format() {
	# DISABLED since newline characters in file names can mess this up.
	# Check if the find commands supports -printf and -mindepth
	#line="$(find "$(command -v bash)" -mindepth 0 -printf "%y %TY-%Tm-%Td %TH:%TM:%TS %s %P\n" 2> /dev/null)"
	#[ "$?" == "0" ] && echo "$line" | grep -Eq '^. [0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)? [0-9]+ $' \
	#	&& mode="find-printf" && format="timestamp" && return 0
	
	# Make sure find supports -print0
	find "$(command -v bash)" -print0 &> /dev/null
	[ "$?" != "0" ] && return 21
	
	# Make sure xargs supports -0
	echo | xargs -0 echo &> /dev/null
	[ "$?" != "0" ] && return 22
	
	# Make sure find outputs results with the correct prefix.
	line="$(cd "$BASH_DIR" &> /dev/null && find . 2> /dev/null | head -n 2 | tail -n 1)"
	[ "${line:0:2}" != "./" ] && return 23
	
	# Make sure ls keeps the prefix.
	line="$(cd "$BASH_DIR" &> /dev/null && ls -d ./bash 2> /dev/null)"
	[ "${line:0:2}" != "./" ] && return 24
	
	# Check if FreeBSD -D <format> argument is available.
	# TODO: Check if works on FreeBSD
	line="$(ls -ld -D '%Y-%m-%d %H:%M:%S' . 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | '{ print $6, substr($7, 1, 8), $8 }' | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} \.$' \
		&& awkarg='{ if (substr($6, 5, 1) substr($6, 8, 1) substr($7, 3, 1) substr($7, 6, 1) == "--::" && $5 ~ /^[0-9]+$/) printf "%s%c%s%c%s%c%s%c%s\n", substr($1, 1, 1), '$delimdec', $6, '$delimdec', substr($7, 1, 8), '$delimdec', $5, '$delimdec', substr($0, index($0, " " $7 " " $8) + length($7) + 4, 1024); else print $0 }' \
		&& mode="ls-awk" && format="timestamp" && lsformatarg="-D '%Y-%m-%d %H:%M:%S'" && return 0
	
	# Check if --time-style is available.
	line="$(ls -ld --time-style='+%Y-%m-%d %H:%M:%S' . 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | awk '{ print $6, substr($7, 1, 8), $8 }' | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} \.$' \
		&& awkarg='{ if (substr($6, 5, 1) substr($6, 8, 1) substr($7, 3, 1) substr($7, 6, 1) == "--::" && $5 ~ /^[0-9]+$/) printf "%s%c%s%c%s%c%s%c%s\n", substr($1, 1, 1), '$delimdec', $6, '$delimdec', substr($7, 1, 8), '$delimdec', $5, '$delimdec', substr($0, index($0, " " $7 " " $8) + length($7) + 4, 1024); else print $0 }' \
		&& mode="ls-awk" && format="timestamp" && lsformatarg="--time-style='+%Y-%m-%d %H:%M:%S'" && return 0
	
	# Check if --full-time is available.
	line="$(ls -ld --full-time . 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | awk '{ print $6, substr($7, 1, 8), $8, $9 }' | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} \+0000 \.$' \
		&& awkarg='{ if (substr($6, 5, 1) substr($6, 8, 1) substr($7, 3, 1) substr($7, 6, 1) == "--::" && $5 ~ /^[0-9]+$/) printf "%s%c%s%c%s%c%s%c%s\n", substr($1, 1, 1), '$delimdec', $6, '$delimdec', substr($7, 1, 8), '$delimdec', $5, '$delimdec', substr($0, index($0, " " $8 " " $9) + length($8) + 4, 1024); else print $0 }' \
		&& mode="ls-awk" && format="timestamp" && lsformatarg="--full-time" && return 0
	
	# Check if -T for displaying full date/time is available.
	line="$(ls -ldT . 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | awk '{ print $6, $7, substr($8, 1, 8), $9, $10 }' | grep -Eq '^[A-Z][a-z]{2} [0-9]{1,2} [0-9]{2}:[0-9]{2}:[0-9]{2} [0-9]{4} \.$' \
		&& awkarg='BEGIN { split("Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec", month, " "); for (i=1; i<=12; i++) mdigit[month[i]] = sprintf("%02d", i) }; { if (substr($8, 3, 1) substr($8, 6, 1) == "::" && $5 ~ /^[0-9]+$/) printf "%s%c%s%c%s%c%s%c%s\n", substr($1, 1, 1), '$delimdec', $9 "-" mdigit[$6] "-" sprintf("%02d", $7), '$delimdec', substr($8, 1, 8), '$delimdec', $5, '$delimdec', substr($0, index($0, " " $8 " " $9 " ") + 17, 1024); else print $0 }' \
		&& mode="ls-awk" && format="timestamp" && lsformatarg="-T" && return 0
	
	# TODO: Possibly allow the default format since we include current date/time as reference? Will not be able to determine exact time for most files however.
	
	return 20
}

function determine_nosort() {
	[ "$mode" == "find-printf" ] && return 0

	# Check for -U version of 'no sort'.
	line="$(ls -ldU .. . 2> /dev/null)"
	[ "$?" == "0" -a "$(echo "$line" | awk '{ print $9 }' | tr '\n' ' ' | awk '{ print $1, $2 }')" == ".. ." ] && lsnosortarg='-U' && return 0
	
	# Check for -f version of 'no sort'.
	# This check must be after -U since -f can turn off -l on some systems.
	line="$(ls -ldf .. . 2> /dev/null)"
	[ "$?" == "0" -a "$(echo "$line" | awk '{ print $9 }' | tr '\n' ' ' | awk '{ print $1, $2 }')" == ".. ." ] && lsnosortarg='-f' && return 0
	
	return 40
}

function determine_escaping() {
	escapedflag=''
	
	# Check for --escape argument.
	line="$(ls -d --escape . 2> /dev/null)"
	[ "$?" == "0" -a "$line" == "." ] && lsescapearg='--escape' && escapedflag='escaped:92' && return 0
	
	# Allow the -b argument to be forced.
	[ "$forceb" == "Y" ] && lsescapearg='-b' && escapedflag='escaped:92' && return 0
	
	# Default and normalize the temp directory path.
	TMPDIR="${TMPDIR:-/tmp}"
	TMPDIR="${TMPDIR%/}"
	
	# Create a temp directory.
	escapetestdir=$(mktemp -d "$TMPDIR/diskusagereports-findsh-escapetest.XXXXXXXXXX" 2> /dev/null)
	
	# Check that directory was created,
	# and create a test file.
	if [ "$?" == "0" -a -n "$escapetestdir" -a -d "$escapetestdir" ] && echo "$escapetestdir" | grep -q "diskusagereports-findsh-escapetest" && touch "$escapetestdir/test	test.txt" 2> /dev/null; then
		
		# Test the -b argument.
		line="$(cd "$escapetestdir"; ls -db "test	test.txt" 2> /dev/null)"
		if [ "$?" == "0" -a "$line" == "test\\ttest.txt" ]; then
			lsescapearg='-b'
			escapedflag='escaped:92'
			ret=0
		
		# ls does not support escaping.
		else
			ret=60
		fi
	
	# Failed to create temp dir.
	else
		ret=61
	fi
	
	if [ -n "$escapetestdir" -a -e "$escapetestdir" ] && echo "$escapetestdir" | grep -q "diskusagereports-findsh-escapetest"; then
		rm -rf "$escapetestdir"
	fi
	
	# Allow to continue without escaping.
	if [ "$ret" != "0" -a "$forcenoescape" == "Y" ]; then
		lsescapearg=''
		ret=0
	fi
	
	return $ret
}

function handle_error() {
	case "$1" in
		21)
			echo "ERROR $1: The 'find' command on this system does not support -print0. Please use scripts/find.php instead." 1>&2
			;;
		22)
			echo "ERROR $1: The 'xargs' command on this system does not support -0. Please use scripts/find.php instead." 1>&2
			;;
		23)
			echo "ERROR $1: The 'find' command on this system failed the path prefix check. Please use scripts/find.php instead." 1>&2
			;;
		24)
			echo "ERROR $1: The 'ls' command on this system failed the path prefix check. Please use scripts/find.php instead." 1>&2
			;;
		20|40)
			echo "ERROR $1: The 'ls' command on this system does not support the features necessary to use this script. Please use scripts/find.php instead." 1>&2
			;;
		60)
			echo "ERROR $1: The 'ls' command on this system does not support escaping unusual characters (e.g. a newline) in file names. See the -ne argument in --help, or use scripts/find.php instead." 1>&2
			;;
		61)
			echo "ERROR $1: Could not create a temporary directory to check if the 'ls' command supports the -b argument. See the -b argument in --help, or use scripts/find.php instead." 1>&2
			;;
	esac
	
	exit $1
}

function syntax() {
	[ "$*" != "" ] && echo "$*" 1>&2
	echo "Syntax: find.sh [-b|-ne] [-d <char|'null'>] [-] <directory-to-scan>" 1>&2
	echo "                [<find-test>, ...]" 1>&2
	echo "        Use -h for full help or visit diskusagereports.com/docs." 1>&2
	exit 1
}

function syntax_long() {
	echo "
Syntax: find.sh [-b|-ne] [-d <char|'null'>] [-] <directory-to-scan>
                [<find-test>, ...]

Arguments:

-b
Force the usage of the 'ls' command's -b argument to escape unusual characters
(e.g. a newline) in file names. Use this flag if you know that 'ls' supports
this argument on your system and you want to skip the use of 'mktemp' to check
for support.

-d <char|'null'>
Optionally specify the field delimiter for each line in the output.
Must be a single ASCII character or the word 'null' for the null character.
The default is the space character.

-ne
Force the script to execute even if the 'ls' command does not support the
--escape or -b arguments. This will cause problems if file names encountered
during the scan contain newlines.

- (minus sign)
If the <directory-to-scan> is the same as one of the options for this script
(e.g. '-d'), you must use a minus sign as an argument before it. You should
do this if you ever expect the <directory-to-scan> to start with a minus sign.

<directory-to-scan>
The directory that the list of sub-directories and files will be created for.

<find-test>
Optionally specify one or more tests that will be passed directly to the
'find' command. You must use the absolute path for any tests that match the
path, such as '-path'. Do not use any expressions that would change the output
of find, such as '-ls'. If using '-type', make sure that you do not exclude
directories. See the 'find' man page for details.

    Expression Examples:
        ! -name '.DS_Store' -a ! -name 'Thumbs.db'
        Exclude extra files created by Windows and Mac OS.

        ! -size 0c
        Exclude files that have a size of zero bytes.

        ! -path '/var/www/html/somesite/*'
        Exclude the contents of a directory from the results.

        ! -path '/var/www/html/somesite' -a ! -path '/var/www/html/somesite/*'
        Completely exclude a directory from the results.

        -type d -a -type f
        Only include directories and regular files.

See also: diskusagereports.com/docs
"
	exit 1
}

# Argument defaults
real=
forceb=
forcenoescape=
delim=" "
delimoct=40
delimdec=32

# Output syntax if there are no arguments.
[ "$#" -eq 0 ] && syntax

# Parse arguments.
while [ "$#" -gt 0 -a -z "$real" ]; do
	if [ "$1" == '-h' -o "$1" == '-?' -o "$1" == '--help' ]; then
		syntax_long
	
	elif [ "$1" == '-b' ]; then
		forceb=Y
		
	elif [ "$1" == '-ne' ]; then
		forcenoescape=Y
		
	elif [ "$1" == '-d' ]; then
		shift
		[ "$#" == "0" ] && syntax "Missing argument for -d."
		
		if [ "$1" == "null" ]; then
			delim="null"
			delimoct=0
			delimdec=0
		else
			[ "$(printf '%o' \'" ")" != "40" ] && echo "ERROR: printf is not outputting expected octal for field delimiter." 1>&2 && exit 2
			[ "$(printf '%d' \'" ")" != "32" ] && echo "ERROR: printf is not outputting expected decimal for field delimiter." 1>&2 && exit 2
			
			delim="$1"
			[ "${#delim}" != '1' ] && syntax "The field delimiter must be exactly one character long."
			delimoct="$(printf '%o' \'"$delim")"
			delimdec="$(printf '%d' \'"$delim")"
		fi
	
	else
		# Allow a single hyphen to indicate that the next argument is the <directory-to-scan>
		[ "$1" == "-" ] && shift
		
		if [ "$#" -gt 0 ]; then
			[ ! -d "$1" ] && syntax "<directory-to-scan> does not exist or is not a directory: $1"
			real=$(cd "$1" && pwd)
		fi
	fi
	
	shift
done

# Make sure the <directory-to-scan> has been set.
[ -z "$real" ] && syntax "The <directory-to-scan> argument is missing."

# Make sure the <directory-to-scan> is a directory.
[ ! -d "$real" ] && syntax "The <directory-to-scan> is not a directory."

# Split the <directory-to-scan>
dir=$(dirname "$real")
base=$(basename "$real")

# Set the dir to an empty string if we are scanning '/'.
if [ "$dir$base" == '//' ]; then
	dir=
	base='/'
fi

# Determine what arguments to use.
determine_format || handle_error $?
determine_nosort || handle_error $?
determine_escaping || handle_error $?

# Check that the <find-test> arguments are supported.
if [ "$#" -gt 0 ]; then
	find "$(command -v bash)" "$@" &> /dev/null
	[ "$?" != "0" ] && echo "ERROR: One or more of the <find-test> arguments are not supported by find." 1>&2 && exit 1
fi

timestamp="$(date '+%Y-%m-%d %H:%M:%S')"
dir="$(echo "$dir" | awk '{ gsub(/\\/, "\\\\"); gsub(/ /, "\\ "); if ( NR > 1 ) printf "%s", "\\n"; printf "%s", $0 }')"
base="$(echo "$base" | awk '{ gsub(/\\/, "\\\\"); gsub(/ /, "\\ "); if ( NR > 1 ) printf "%s", "\\n"; printf "%s", $0 }')"
echo "## v2 $delimdec 47 ${timestamp:0:19} $escapedflag datetimeformat:$format dirname:$dir basename:$base"

if [ "$mode" == "find-printf" ]; then
	
	# Verify that the delim will output correctly.
	[ "$delim" == "null" ] && [ "$(find "$(command -v bash)" -printf "\\$delimoct" | wc -c)" != "1" -o "$(find "$(command -v bash)" -printf "\\$delimoct" | tr -d '\0' | wc -c)" != "0" ] \
		&& echo "ERROR: find is not outputting the expected null field delimiter." 1>&2 && exit 2
	[ "$delim" != "null" -a "$(find "$(command -v bash)" -printf "\\$delimoct")" != "$delim" ] \
		&& echo "ERROR: find is not outputting the expected field delimiter." 1>&2 && exit 2
	
	cd "$real"
	find . -mindepth 1 "$@" -printf "%y\\$delimoct%TY-%Tm-%Td\\$delimoct%TH:%TM:%TS\\$delimoct%s\\$delimoct%P\n"
else
	
	# Verify that the delim will output correctly.
	[ "$delim" == "null" ] && [ "$(echo | awk '{ printf "%c", '$delimdec' }' | wc -c | tr -d '[:blank:]')" != "1" -o "$(echo | awk '{ printf "%c", '$delimdec' }' | tr -d '\0' | wc -c | tr -d '[:blank:]')" != "0" ] \
		&& echo "ERROR: awk is not outputting the expected null field delimiter." 1>&2 && exit 2
	[ "$delim" != "null" -a "$(echo | awk '{ printf "%c", '$delimdec' }')" != "$delim" ] \
		&& echo "ERROR: awk is not outputting the expected field delimiter." 1>&2 && exit 2
	
	cd "$real"
	find . "$@" -print0 | eval xargs -0 ls -ld $lsformatarg $lsnosortarg $lsescapearg | tail -n +2 | awk "$awkarg"
fi

exit 0
