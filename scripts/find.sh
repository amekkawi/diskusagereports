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
	line="$(find "$0" -mindepth 0 -printf "%y %TY-%Tm-%Td %TH:%TM:%TS %s %P\n" 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | grep -Eq '^. [0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} [0-9]+ $' \
		&& mode="find-printf" && format="timestamp" && return 0
	
	# Make sure find supports -print0
	find "$0" -print0 &> /dev/null
	[ "$?" != "0" ] && return 50
	
	# Make sure xargs supports -0
	echo | xargs -0 echo &> /dev/null
	[ "$?" != "0" ] && return 53
	
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
		&& awkarg='{ printf "%s%c%s%c%s%c%s%c%s\n", substr($1, 0, 1), '$delimdec', $6, '$delimdec', substr($7, 0, 8), '$delimdec', $5, '$delimdec', substr($0, index($0, " " $7 " " $8) + length($7) + 4, 1024) }' \
		&& mode="ls-awk" && format="timestamp" && lsarg="-D '%Y-%m-%d %H:%M:%S'" && return 0
	
	# Check if --time-style is available.
	line="$(ls -ld --time-style='+%Y-%m-%d %H:%M:%S' . 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | awk '{ print $6, substr($7, 0, 8), $8 }' | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} \.$' \
		&& awkarg='{ printf "%s%c%s%c%s%c%s%c%s\n", substr($1, 0, 1), '$delimdec', $6, '$delimdec', substr($7, 0, 8), '$delimdec', $5, '$delimdec', substr($0, index($0, " " $7 " " $8) + length($7) + 4, 1024) }' \
		&& mode="ls-awk" && format="timestamp" && lsarg="--time-style='+%Y-%m-%d %H:%M:%S'" && return 0
	
	# Check if --full-time is available.
	line="$(ls -ld --full-time . 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | awk '{ print $6, $7, $8, $9 }' | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]+ \+0000 \.$' \
		&& awkarg='{ printf "%s%c%s%c%s%c%s%c%s\n", substr($1, 0, 1), '$delimdec', $6, '$delimdec', substr($7, 0, 8), '$delimdec', $5, '$delimdec', substr($0, index($0, " " $8 " " $9) + length($8) + 4, 1024) }' \
		&& mode="ls-awk" && format="timestamp" && lsarg="--full-time" && return 0
	
	# Check if -T for displaying full date/time is available.
	line="$(ls -ldT . 2> /dev/null)"
	[ "$?" == "0" ] && echo "$line" | awk '{ print $6, $7, $8, $9, $10 }' | grep -Eq '^[A-Z][a-z]{2} [0-9]{1,2} [0-9]{2}:[0-9]{2}:[0-9]{2} [0-9]{4} \.$' \
		&& awkarg='BEGIN { split("Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec", month, " "); for (i=1; i<=12; i++) mdigit[month[i]] = sprintf("%02d", i) }; { printf "%s%c%s%c%s%c%s%c%s\n", substr($1, 0, 1), '$delimdec', $9 "-" mdigit[$6] "-" sprintf("%02d", $7), '$delimdec', $8, '$delimdec', $5, '$delimdec', substr($0, index($0, " " $8 " " $9 " ") + 17, 1024) }' \
		&& mode="ls-awk" && format="timestamp" && lsarg="-T" && return 0
	
	# // TODO: Possibly allow the default format since we include current date/time? Will not be able to determine time however.
	
	return 200
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
	
	return 201
}

function syntax() {
	[ "$*" != "" ] && echo "$*" 1>&2
	echo "Syntax: $(basename "$0") [-d <char|'null'>] [-] <directory-to-scan> [<find-test>, ...]" 1>&2
	echo "Use -h for full help or visit diskusagereports.com/docs." 1>&2
	exit 1
}

function syntax_long() {
	echo "Syntax: $(basename "$0") [-d <char|'null'>] [-] <directory-to-scan> [<find-test>, ...]

Arguments:

-d <char|'null'>
Optionally specify the field delimiter for each line in the output.
Must be a single ASCII character or the word 'null' for the null character.
The default is the space character.

- (minus sign)
If the <directory-to-scan> is the same as one of the options for this script
(e.g. "-d"), you must use a minus sign as an argument before it. You should
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

# Parse arguments.
while [ "$#" -gt 0 -a -z "$real" ]; do
	if [ "$1" == '-h' -o "$1" == '-?' -o "$1" == '--help' ]; then
		syntax_long
	
	elif [ "$1" == '-d' ]; then
		shift
		[ "$delim" != "" ] && syntax "Field delimiter already set."
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
			[ ! -d "$1" ] && syntax "<directory-to-scan> does not exist or is not a directory: $1" 1>&2
			real=$(cd "$1" && pwd)
		fi
	fi
	
	shift
done

# Make sure the <directory-to-scan> has been set.
[ "$real" == "" ] && syntax "The <directory-to-scan> argument is missing." 1>&2

# Make sure the <directory-to-scan> is a directory.
[ ! -d "$real" ] && syntax "The <directory-to-scan> is not a directory." 1>&2

# Check that the <find-test> arguments are supported.
find "$0" "$@" &> /dev/null
[ "$?" != "0" ] && echo "ERROR: One or more of the <find-test> arguments are not supported by find." 1>&2 && exit 2

# Split the <directory-to-scan>
dir=$(dirname "$real")
base=$(basename "$real")

# Default the delim to a space
[ "$delim" == "" ] && delim=" " && delimoct=40 && delimdec=32

# Set the dir to an empty string if we are scanning '/'.
if [ "$dir$base" == '//' ]; then
	dir=
	base='/'
fi

# Determine the output format/method
determine_format
ret="$?"
[ "$ret" != "0" ] && echo "ERROR: The commands on this system do not support the features necessary to use this script (error $ret). Please use scripts/find.php instead." 1>&2 && exit $ret

determine_nosort
ret="$?"
[ "$ret" != "0" ] && echo "ERROR: The commands on this system do not support the features necessary to use this script (error $ret). Please use scripts/find.php instead." 1>&2 && exit $ret

timestamp="$(date '+%Y-%m-%d %H:%M:%S')"
echo "## v2 $delimdec 47 ${timestamp:0:19} $format $(echo "$dir" | sed -e 's/\\/\\\\/g' -e 's/ /\\ /g') $(echo "$base" | sed -e 's/\\/\\\\/g' -e 's/ /\\ /g')"

if [ "$mode" == "find-printf" ]; then
	
	# Verify that the delim will output correctly.
	[ "$delim" == "null" ] && [ "$(find "$0" -printf "\\$delimoct" | wc -c)" != "1" -o "$(find "$0" -printf "\\$delimoct" | tr -d '\0' | wc -c)" != "0" ] \
		&& echo "ERROR: find is not outputting the expected null field delimiter." 1>&2 && exit 2
	[ "$delim" != "null" -a "$(find "$0" -printf "\\$delimoct")" != "$delim" ] \
		&& echo "ERROR: find is not outputting the expected field delimiter." 1>&2 && exit 2
	
	find "$real" -mindepth 1 "$@" -printf "%y\\$delimoct%TY-%Tm-%Td\\$delimoct%TH:%TM:%TS\\$delimoct%s\\$delimoct%P\n"
else
	
	# Verify that the delim will output correctly.
	[ "$delim" == "null" ] && [ "$(echo | awk '{ printf "%c", '$delimdec' }' | wc -c | tr -d '[:blank:]')" != "1" -o "$(echo | awk '{ printf "%c", '$delimdec' }' | tr -d '\0' | wc -c | tr -d '[:blank:]')" != "0" ] \
		&& echo "ERROR: awk is not outputting the expected null field delimiter." 1>&2 && exit 2
	[ "$delim" != "null" -a "$(echo | awk '{ printf "%c", '$delimdec' }')" != "$delim" ] \
		&& echo "ERROR: awk is not outputting the expected field delimiter." 1>&2 && exit 2
	
	cd "$real"
	find . "$@" -print0 | eval xargs -0 ls -ld $lsarg $lsnosortarg | tail -n +2 | awk "$awkarg"
fi

exit 0
