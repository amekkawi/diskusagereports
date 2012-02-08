/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * Revision: $Revision$
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

#include "StdAfx.h"
#include "Finder.h"
#include <sys/stat.h>
#include <algorithm>

using namespace std;

//const char* CFINDER_VERSION = "$Revision$";

char* CFinder::Version(void) {
	return "$Revision$";
}

CFinder::CFinder(void) {
	ds = '\\';
	_tds = _T('\\');
	
	delim = '\0';
	_tdelim = _T('\0');
}

void CFinder::setDelim(_TCHAR _tdelim) {
	char* delim = CFinder::UnicodeToUTF8(_tdelim);
	
	if (strlen(delim) == 1) {
		this->_tdelim = _tdelim;
		this->delim = delim[0];
	}

	delete[] delim;
}

void CFinder::setDS(_TCHAR _tds) {
	char* ds = CFinder::UnicodeToUTF8(_tds);
	
	if (strlen(ds) == 1) {
		this->_tds = _tds;
		this->ds = ds[0];
	}

	delete[] ds;
}

int CFinder::run(_TCHAR* directory) {

	_TCHAR shit[MAX_PATH];
	combinePath(shit, 2, _T("a"), _T("b"));

	// Determine the real (aka: absolute) path.
	_TCHAR realPath[MAX_PATH];
	_tfullpath(realPath, directory, MAX_PATH);

	// Fail if the real path could not be determined.
	if (realPath == NULL) {
		return CFinder::ERROR_DIRECTORY_CANTRESOLVE;
	}

	// Note: This also changes directory to a buffer of MAX_PATH length.
	directory = realPath;

	// Split the directory into parts
	CFinder::SPLIT_PATH_DATA dirSplit;
	CFinder::SplitPath(directory, &dirSplit);

	// Convert directory path to string.
	_tstring sdir (directory);

	// Do not trim slashes if the drive is specified and the
	// path after the drive is only a directory separator.
	if (_tcslen(dirSplit.drive) == 0 || _tcslen(dirSplit.basename) != 0 || dirSplit.dir != _T("\\")) {

		// Trim trailing slashes.
		_tstring::size_type lastNotSlash = sdir.find_last_not_of(_T("\\"));
		if (lastNotSlash != -1 && lastNotSlash + 1 < sdir.size()) {
			sdir.erase(lastNotSlash + 1);
		}
	}
	
	// Copy modified directory back.
	_tcscpy_s(directory, MAX_PATH, sdir.c_str());

	// Attempt to get directory attributes.
	DWORD dattr = GetFileAttributes(directory);
	if (dattr == INVALID_FILE_ATTRIBUTES) {
		DWORD dattrError = GetLastError();
		switch (dattrError) {
			case ERROR_BAD_NETPATH:
			case ERROR_PATH_NOT_FOUND:
			case ERROR_FILE_NOT_FOUND:
			case ERROR_INVALID_NAME:
				return CFinder::ERROR_DIRECTORY_NOTFOUND;
			case ERROR_ACCESS_DENIED:
				return CFinder::ERROR_DIRECTORY_ACESSDENIED;
			default:
				return CFinder::ERROR_DIRECTORY_STAT;
		}
	}
	else if (!(dattr & FILE_ATTRIBUTE_DIRECTORY)) {
		return CFinder::ERROR_DIRECTORY_NOTFOUND;
	}

	outputHeader(&dirSplit);

	processDirectory(directory, _T(""), 1);

	return 0;
}

void CFinder::outputHeader(CFinder::SPLIT_PATH_DATA* dirSplit) {
	SYSTEMTIME now;
	GetSystemTime(&now);

	// Output the header.
	cout << "#";
	fwrite(&delim, 1, 1, stdout);
	
	// Replace directory separators if a different one was specified.
	replacePathDS(dirSplit->dirname);

	char* dirnameUTF8 = CFinder::UnicodeToUTF8(dirSplit->dirname);
	char* basenameUTF8 = CFinder::UnicodeToUTF8(dirSplit->basename);

	// Flip the dirname and basename if the basename is empty.
	// This can happen if the path is "c:\\"
	if (strlen(basenameUTF8) == 0) {
		char* tmp = basenameUTF8;
		basenameUTF8 = dirnameUTF8;
		dirnameUTF8 = tmp;
		tmp = NULL;
	}
	
	fwrite(&ds, 1, 1, stdout);
	fwrite(&delim, 1, 1, stdout);

	cout << dirnameUTF8;
	fwrite(&delim, 1, 1, stdout);

	cout << basenameUTF8;
	fwrite(&delim, 1, 1, stdout);
	
	delete[] dirnameUTF8;
	delete[] basenameUTF8;
	
	// Output date/time
	printf(
		"%04d-%02d-%02d %02d:%02d:%02d",
		now.wYear, now.wMonth, now.wDay,
		now.wHour, now.wMinute, now.wSecond
	);
	
	cout << endl;
}

void CFinder::processDirectory(_TCHAR* rootPath, _TCHAR* pathExt, int depth) {
	processDirectory(rootPath, pathExt, depth, false);
}

void CFinder::processDirectory(_TCHAR* rootPath, _TCHAR* pathExt, int depth, bool exact) {
	_TCHAR fullPath[MAX_PATH];

	// Use the \\?\ path prefix if exact.
	if (exact) {
		_tstring rootPathS(rootPath);
		
		// UNC path
		if (_tcslen(rootPath) >= 2 && rootPath[0] == _T('\\') && rootPath[1] == _T('\\')) {
			rootPathS.insert(2, _T("?\\UNC\\"));
		}
		else {
			rootPathS.insert(0, _T("\\\\?\\"));
		}

		// Create a temp rootPath
		_TCHAR rootPathTmp[MAX_PATH];
		_tcscpy_s(rootPathTmp, MAX_PATH, rootPathS.c_str());

		combinePath(fullPath, 3, rootPathTmp, pathExt, _T("*"));
	}
	else {
		combinePath(fullPath, 3, rootPath, pathExt, _T("*"));
	}

	WIN32_FIND_DATA findData;
	HANDLE hFind = INVALID_HANDLE_VALUE;
	
	hFind = FindFirstFile(fullPath, &findData);
	if (hFind == INVALID_HANDLE_VALUE) {

		DWORD lastError = GetLastError();
		if (!exact && lastError == ERROR_PATH_NOT_FOUND) {
			// Try again with exact path, if path was not found and not already exact.
			processDirectory(rootPath, pathExt, depth, true);
		}
		else if (lastError != ERROR_NO_MORE_FILES) {
			cerr << "Failed to open directory for listing files (";

			switch (lastError) {
				case ERROR_BAD_NETPATH:
					cerr << "BAD_NETPATH";
					break;
				case ERROR_PATH_NOT_FOUND:
					cerr << "PATH_NOT_FOUND";
					break;
				case ERROR_FILE_NOT_FOUND:
					cerr << "FILE_NOT_FOUND";
					break;
				case ERROR_INVALID_NAME:
					cerr << "INVALID_NAME";
					break;
				case ERROR_ACCESS_DENIED:
					cerr << "ACCESS_DENIED";
					break;
				default:
					fprintf(stderr, "code %d: ", lastError);
					cerr << "see diskusagereports.com/docs/errorcodes.html";
			}

			char* fullPathUTF8 = CFinder::UnicodeToUTF8(fullPath);
			cerr << "): " << fullPathUTF8 << endl;
			delete[] fullPathUTF8;

			outputError("OPENDIR_FAIL", pathExt);
		}
	}
	else {
		do {
			if (_tcscmp(findData.cFileName, _T(".")) != 0
				&& _tcscmp(findData.cFileName, _T("..")) != 0) {
			
				processEntry(rootPath, pathExt, depth, findData, exact);
			}
		} while (FindNextFile(hFind, &findData));
	}
}

void CFinder::processEntry(_TCHAR* rootPath, _TCHAR* pathExt, int depth, WIN32_FIND_DATA findData, bool exact) {
	char type;

	if (findData.dwFileAttributes & FILE_ATTRIBUTE_REPARSE_POINT) {
		type = 'l';
	}
	else if (findData.dwFileAttributes & FILE_ATTRIBUTE_DIRECTORY) {
		type = 'd';
	}
	else {
		type = 'f';
	}

	outputEntry(type, pathExt, depth, findData);
	
	// List contents if entry is a directory.
	if (type == 'd') {
		_TCHAR subPathExt[MAX_PATH];
		combinePath(subPathExt, 3, _T(""), pathExt, findData.cFileName);
		processDirectory(rootPath, subPathExt, depth + 1, exact);
	}
}

void CFinder::outputEntry(char type, _TCHAR* pathExt, int depth, WIN32_FIND_DATA findData) {
	_TCHAR path[MAX_PATH];
	combinePath(path, 3, _T(""), pathExt, findData.cFileName);

	// Replace directory separators if a different one was specified.
	replacePathDS(path);
	
	char* utf8Name = CFinder::UnicodeToUTF8(path);
	
	// Use a large int to combine the two size parts.
	LARGE_INTEGER fileSize;
	fileSize.LowPart = findData.nFileSizeLow;
	fileSize.HighPart = findData.nFileSizeHigh;

	// Convert the file's write time (which is UTC) to system time (which is also UTC).
	SYSTEMTIME writeTime;
	FileTimeToSystemTime(&findData.ftLastWriteTime, &writeTime);
	
	// Output type char.
	cout << type;
	fwrite(&delim, 1, 1, stdout);
	
	// Output date/time
	printf("%04d-%02d-%02d", writeTime.wYear, writeTime.wMonth, writeTime.wDay);
	fwrite(&delim, 1, 1, stdout);
	printf("%02d:%02d:%02d", writeTime.wHour, writeTime.wMinute, writeTime.wSecond);
	fwrite(&delim, 1, 1, stdout);
	
	// Output file size.
	printf("%I64d", fileSize.QuadPart);
	fwrite(&delim, 1, 1, stdout);

	// Output depth and file name.
	cout << depth;
	fwrite(&delim, 1, 1, stdout);
	cout << utf8Name << endl;
	
	delete[] utf8Name;
}

void CFinder::outputError(char* code, _TCHAR* pathExt) {
	// Create a copy of the path.
	_TCHAR pathExtTmp[MAX_PATH];
	_tcscpy_s(pathExtTmp, MAX_PATH, pathExt);

	replacePathDS(pathExtTmp);

	char* pathExtUTF8 = CFinder::UnicodeToUTF8(pathExtTmp);

	cout << "!";
	fwrite(&delim, 1, 1, stdout);
	
	cout << code;
	fwrite(&delim, 1, 1, stdout);
	
	cout << pathExtUTF8 << endl;

	delete[] pathExtUTF8;
}

void CFinder::combinePath(_TCHAR* combined, int parts, ...) {
	_tstring combinedS;

	_TCHAR* part;
	va_list marker;

	va_start( marker, parts );
	for (int i = 0; i < parts; i++) {
		part = va_arg( marker, _TCHAR*);

		if (_tcslen(part) != 0) {
			combinedS += (combinedS.size() == 0 ? _T("") : _T("\\")) + _tstring(part);
		}
	}
	va_end(marker);

	_tcscpy_s(combined, MAX_PATH, combinedS.c_str());
}

char* CFinder::UnicodeToUTF8(_TCHAR cunicode) {
	_TCHAR unicode[2] = { cunicode, _T('\0') };
	return CFinder::UnicodeToUTF8(unicode);
}

char* CFinder::UnicodeToUTF8(_TCHAR* unicode) {
	int bufferSize = WideCharToMultiByte(CP_UTF8, 0, unicode, -1, NULL, 0, NULL, NULL);
	char* utf8 = new char[bufferSize]; 
	WideCharToMultiByte(CP_UTF8, 0, unicode, -1, utf8, bufferSize, NULL, NULL);
	return utf8;
}

void CFinder::SplitPath(_TCHAR* path, SPLIT_PATH_DATA* data) {
	_tsplitpath_s(path, data->drive, _MAX_DRIVE, data->dir, _MAX_DIR, data->fname, _MAX_FNAME, data->ext, _MAX_EXT);

	// Combine drive and dir to make dirname.
	_tstring dirnameS(data->drive);
	dirnameS += _tstring(data->dir);
	
	// Trim trailing slash (/)
	if (dirnameS.size() != 0) {
		_tstring::size_type lastNotSlash = dirnameS.find_last_not_of(_T("\\"));
		if (lastNotSlash != -1 && lastNotSlash + 1 < dirnameS.size()) {
			dirnameS.erase(lastNotSlash + 1);
		}
	}

	// Copy dirname to the struct
	data->dirname = new _TCHAR[dirnameS.size() + 1];
	_tcscpy_s(data->dirname, dirnameS.size() + 1, dirnameS.c_str());

	// Combine fname and ext to make the basename.
	_tstring basenameS(data->fname);
	basenameS += _tstring(data->ext);
	
	// Copy basename to the struct
	data->basename = new _TCHAR[basenameS.size() + 1];
	_tcscpy_s(data->basename, basenameS.size() + 1, basenameS.c_str());
}

CFinder::SPLIT_PATH_DATA::~SPLIT_PATH_DATA() {
	delete[] basename;
	delete[] dirname;
}

void CFinder::replacePathDS(_TCHAR* path) {
	// Replace directory separators if a different one was specified.
	if (_tds != _T('\\')) {
		for (size_t i = 0; i < _tcslen(path); i++) {
			if (path[i] == _T('\\')) {
				path[i] = _tds;
			}
		}
	}
}