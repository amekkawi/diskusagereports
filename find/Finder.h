/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

#pragma once

#include "StdAfx.h"
#include "PathHelper.h"
#include <sys/stat.h>
#include <algorithm>

class CFinder {

public:
	struct SPLIT_PATH_DATA {
		_TCHAR drive[_TMAX_DRIVE];
		_TCHAR dir[_TMAX_DIR];
		_TCHAR fname[_TMAX_FNAME];
		_TCHAR ext[_TMAX_EXT];

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

	static char* UnicodeToUTF8(const _TCHAR unicode);
	static char* UnicodeToUTF8(const _TCHAR* unicode);
	static bool SplitPath(_TCHAR* path, SPLIT_PATH_DATA& data);
	static void MakePathExtendedLength(_TCHAR* path, _TCHAR* extended, int maxLength);
	static char* Version();
	
private:
	char delim;
	_TCHAR _tdelim;
	
	char ds;
	_TCHAR _tds;

	CPathHelper path;

	void processDirectory(_TCHAR* name, int depth);
	void processEntry(int depth, WIN32_FIND_DATA findData);
	
	void outputEntry(char type, int depth, WIN32_FIND_DATA findData);
	void outputHeader();
	void outputError(char* code);

	void replacePathDS(_TCHAR* path);
};

