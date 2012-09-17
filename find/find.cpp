/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * Revision: $Revision$
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

#include "stdafx.h"
#include "Finder.h"

using namespace std;

const char* SYNTAX = "Syntax: find.exe [-d <char|'null'>] [-ds <char>] [-] <directory-to-scan>\nUse -h for full help or visit diskusagereports.com/docs.";
const char* VERSION = "$Source Version$";

int _tmain(int argc, _TCHAR* argv[]) {

	/*_TCHAR fullPath[_TMAX_PATH];
	if (_tfullpath(fullPath, _T(":\\"), _TMAX_PATH) == NULL) {
		return 0;
	}
	
	_TCHAR drive[_TMAX_DRIVE];
	_TCHAR dir[_TMAX_DIR];
	_TCHAR fname[_TMAX_FNAME];
	_TCHAR ext[_TMAX_EXT];

	stack<_TCHAR*> paths;
	paths.push(_T(""));

	paths.push(_T("c:"));
	paths.push(_T("c:\\"));
	paths.push(_T("\\\\?\\c:"));
	paths.push(_T("\\\\?\\c:\\"));
	paths.push(_T("\\\\?\\c:\\wang"));

	paths.push(_T("\\\\chaos"));
	paths.push(_T("\\\\chaos\\wang"));
	paths.push(_T("\\\\?\\UNC\\chaos"));
	paths.push(_T("\\\\?\\UNC\\chaos\\wang"));

	while (paths.size() != 0) {
		if (_tsplitpath_s(paths.top(), drive, _TMAX_DRIVE, dir, _TMAX_DIR, fname, _TMAX_FNAME, ext, _TMAX_EXT) != 0) {
			int shit = 0;
		}
		paths.pop();
	}

	return 0;*/

	_TCHAR* directory = NULL;
	CFinder finder;
	
	for (int i = 1; i < argc; i++) {
		if (_tcscmp(argv[i], _T("-v")) == 0) {
			
			cout << "Disk Usage Reports <http://diskusagereports.com/>" << endl
				<< "Version: " << VERSION << endl;
			
			return 0;
		}
		else if (_tcscmp(argv[i], _T("-?")) == 0
			|| _tcscmp(argv[i], _T("-h")) == 0
			|| _tcscmp(argv[i], _T("/?")) == 0
			|| _tcscmp(argv[i], _T("/h")) == 0) {
			
			cerr << endl
				<< "Syntax: find.exe [-d <char|'null'>] [-ds <char>] [-] <directory-to-scan>" << endl
				<< endl
				<< "Arguments:" << endl
				<< endl
				<< "-d <char|'null'>" << endl
				<< "Optionally specify the field delimiter for each line in the output." << endl
				<< "Must be a single ASCII character or the word 'null' for the null character." << endl
				<< "The default is the space character." << endl
				<< endl
				<< "-ds <directoryseparator>" << endl
				<< "Optionally specify the directory separator used between directory names." << endl
				<< "The default is the backslash (\\) character." << endl
				<< endl
				<< "- (hyphen)" << endl
				<< "If the <directory-to-scan> is the same as one of the arguments for this script" << endl
				<< "(e.g. '-d'), you must use a minus sign as an argument before it. You should" << endl
				<< "do this if you ever expect the <directory-to-scan> to start with a minus sign." << endl
				<< endl
				<< "<directory-to-scan>" << endl
				<< "The directory that the list of sub-directories and files will be created for." << endl
				<< endl
				<< "See also: diskusagereports.com/docs" << endl;

			return 0;
		}
		else if (_tcscmp(argv[i], _T("-d")) == 0) {
			if (++i == argc) {
				cerr << "-d must be followed by an argument." << endl;
				cerr << SYNTAX << endl;
				return 1;
			}
			
			if (_tcscmp(argv[i], _T("null")) == 0
				|| _tcscmp(argv[i], _T("NULL")) == 0
				|| _tcscmp(argv[i], _T("Null")) == 0) {
					finder.setDelim(_T('\0'));
			}
			else if (_tcslen(argv[i]) != 1) {
				cerr << "The argument after -d must be one character long." << endl;
				cerr << SYNTAX << endl;
				return 1;
			}
			else {
				char* delimUTF8 = CFinder::UnicodeToUTF8(argv[i]);
				int delimSize = strlen(delimUTF8);
				delete[] delimUTF8;

				if (delimSize != 1) {
					cerr << "The argument after -d cannot be a multi-byte character." << endl;
					cerr << SYNTAX << endl;
					return 1;
				}

				finder.setDelim(argv[i][0]);
			}
		}
		else if (_tcscmp(argv[i], _T("-ds")) == 0) {
			if (++i == argc) {
				cerr << "-ds must be followed by an argument" << endl;
				cerr << SYNTAX << endl;
				return 1;
			}

			if (_tcslen(argv[i]) != 1) {
				cerr << "The argument after -ds must be one character long." << endl;
				cerr << SYNTAX << endl;
				return 1;
			}

			char* dsUTF8 = CFinder::UnicodeToUTF8(argv[i]);
			int dsSize = strlen(dsUTF8);
			delete[] dsUTF8;

			if (dsSize != 1) {
				cerr << "The argument after -ds cannot be a multi-byte character." << endl;
				cerr << SYNTAX << endl;
				return 1;
			}

			finder.setDirSeparator(argv[i][0]);
		}
		else {
			
			if (_tcscmp(argv[i], _T("-")) == 0 && ++i == argc) {
				cerr << "The - (hyphen) argument must be followed by <directory-to-scan>." << endl;
				cerr << SYNTAX << endl;
				return 1;
			}

			if (_tcslen(argv[i]) > MAX_PATH) {
				cerr << "The <directory-to-scan> argument cannot be longer than " << MAX_PATH << " characters." << endl;
				cerr << SYNTAX << endl;
				return 1;
			}

			directory = argv[i];
		}
	}

	if (directory == 0) {
		cerr << "The <directory-to-scan> argument is missing." << endl;
		cerr << SYNTAX << endl;
		return 1;
	}

	int ret = finder.run(directory);

	switch (ret) {
		case CFinder::ERROR_DIRECTORY_CANTRESOLVE:
			cerr << "Failed to resolve <directory-to-scan> to its full path. You may not have access (read and exec) to the directory or its parent directories." << endl;
			break;
		case CFinder::ERROR_DIRECTORY_NOTFOUND:
			cerr << "The <directory-to-scan> does not exist or is not a directory." << endl;
			break;
		case CFinder::ERROR_DIRECTORY_STAT:
		case CFinder::ERROR_DIRECTORY_ACESSDENIED:
			cerr << "Failed to retrieve info (via GetFileAttributes) on <directory-to-scan>. You may not have access to the directory or its parent directories." << endl;
		case CFinder::ERROR_DIRECTORY_CANTSPLIT:
			cerr << "Failed to split <directory-to-scan> into basename and dirname parts." << endl;
			break;
	}

	return ret;
}

