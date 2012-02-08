/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * Revision: $Revision$
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

#pragma once

class CFinder {

public:
	struct SPLIT_PATH_DATA {
		_TCHAR drive[_MAX_DRIVE];
		_TCHAR dir[_MAX_DIR];
		_TCHAR fname[_MAX_FNAME];
		_TCHAR ext[_MAX_EXT];

		_TCHAR* dirname;
		_TCHAR* basename;
		 ~SPLIT_PATH_DATA();
	};

	const static int ERROR_DIRECTORY_NOTFOUND = 1;
	const static int ERROR_DIRECTORY_ACESSDENIED = 2;
	const static int ERROR_DIRECTORY_STAT = 3;
	const static int ERROR_DIRECTORY_CANTRESOLVE = 4;

	CFinder(void);

	void setDelim(_TCHAR delim);
	void setDS(_TCHAR delim);
	int run(_TCHAR* directory);

	static char* UnicodeToUTF8(_TCHAR unicode);
	static char* UnicodeToUTF8(_TCHAR* unicode);
	static void SplitPath(_TCHAR* path, SPLIT_PATH_DATA* data);
	static char* Version();
	
private:
	char delim;
	_TCHAR _tdelim;
	
	char ds;
	_TCHAR _tds;

	void processDirectory(_TCHAR* rootPath, _TCHAR* pathExt, int depth);
	void processDirectory(_TCHAR* rootPath, _TCHAR* pathExt, int depth, bool exact);
	void processEntry(_TCHAR* rootPath, _TCHAR* pathExt, int depth, WIN32_FIND_DATA findData, bool exact);
	
	void outputEntry(char type, _TCHAR* pathExt, int depth, WIN32_FIND_DATA findData);
	void outputHeader(SPLIT_PATH_DATA* dirSplit);
	void outputError(char* code, _TCHAR* pathExt);

	void replacePathDS(_TCHAR* path);
	void combinePath(_TCHAR* combined, int parts, ...);
};

