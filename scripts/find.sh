#!/bin/bash

# Author: André Mekkawi
# Version: $Revision$
# License See LICENSE.txt or http://diskusagereports.com/license.html 
# Copyright: 2012 André Mekkawi <contact@andremekkawi.com>

# Set the timezone to UTC
export TZ=UTC

function syntax() {
	[ "$*" != "" ] && echo "$*" 1>&2
	echo "Syntax: $0 [OPTIONS] <directory-to-list>" 1>&2
	echo "Use -h for full help or visit diskusagereports.com/docs." 1>&2
	exit 1
}

function syntax_long() {
	echo "Syntax: $0 [OPTIONS] <directory-to-list>

<directory-to-scan>
The directory that the list of sub-directories and files will be created for.

The OPTIONS are:

      -d <delim>
      The field delimiter for each line in the output.
      The default is the NULL character.

See also: diskusagereports.com/docs
"
	exit 1
}

while (( "$#" )); do
	if [ "$1" == '-h' -o "$1" == '-?' -o "$1" == '--help' ]; then
		syntax_long
	
	elif [ "$1" == '-d' ]; then
		shift
		[ "$delim" != "" ] && syntax "Field delimiter already set."
		[ "$#" == "0" ] && syntax "Missing argument for -d."
		
		delim="$1"
		[ "${#delim}" != '1' ] && syntax "The field delimiter must be exactly one character long."
	else
		[ "$real" != "" ] && syntax "Argument not expected: $1"
		[ ! -d "$1" ] && syntax "<directory-to-list> does not exist or is not a directory: $real" 1>&2
		real=$(cd "$1" && pwd)
	fi
	
	shift
done

[ "$real" == "" ] && syntax "The <directory-to-list> argument is missing." 1>&2

#real=$(cd "$1"; pwd)
dir=$(dirname "$real")
base=$(basename "$real")
[ "$delim" == "" ] && delim='\0'

if [ "$dir$base" == '//' ]; then
	dir=
	base='/'
fi

# Output the header
printf "#$delim/$delim"
echo -n $dir
printf "$delim"
echo -n $base
printf "$delim"
date '+%Y-%m-%d %H:%M:%S'

find "$real" -mindepth 1 -printf "%y$delim%TY-%Tm-%Td$delim%TT$delim%s$delim%d$delim%P\n"