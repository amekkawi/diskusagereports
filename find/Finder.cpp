/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

#include "StdAfx.h"
#include "Finder.h"

using namespace std;

CFinder::CFinder(void) {
	dirSeparator = '\\';
	_tdirSeparator = _T('\\');
	
	delim = ' ';
	_tdelim = _T(' ');

	followLinks = false;
}

void CFinder::setDelim(_TCHAR delim) {
	if (delim == _T('\0')) {
		this->_tdelim = delim;
		this->delim = '\0';
	}
	else {
		char* delimUTF8 = CFinder::UnicodeToUTF8(delim);
	
		if (strlen(delimUTF8) == 1) {
			this->_tdelim = delim;
			this->delim = delimUTF8[0];
		}

		delete[] delimUTF8;
	}
}

void CFinder::setDirSeparator(_TCHAR separator) {
	char* separatorUTF8 = CFinder::UnicodeToUTF8(separator);
	
	if (strlen(separatorUTF8) == 1) {
		this->_tdirSeparator = separator;
		this->dirSeparator = separatorUTF8[0];
	}

	delete[] separatorUTF8;
}

void CFinder::setFollowLinks(bool flag) {
	this->followLinks = flag;
}

int CFinder::run(_TCHAR* directory) {

	//directory = _T("\\\\?\\C:\\test\\long\\d23456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890");
	//directory = _T("\\\\?\\C:\\test\\long\\d23456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890\\abc123asd123easd");
	//directory = _T("C:\\test\\long\\..\\long\\d23456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890\\a\\abcdef1234567890b.txt");
	//directory = _T("C:\\test");

	path.setSeparator(_tdirSeparator);
	path.setEscapeChar(_tdirSeparator == _T('\\') ? _T(':') : _T('\\'));

	// Determine the full (aka: absolute) path (only if the path is not extended length format).
	if (CPathHelper::DetectPrefixType(directory) == CPathHelper::PREFIX_NONE) {
		_TCHAR fullPath[_TMAX_PATH];
		if (_tfullpath(fullPath, directory, _TMAX_PATH) == NULL) {
			return CFinder::ERROR_DIRECTORY_CANTRESOLVE;
		}
		
		path = fullPath;
	}
	else {
		path = directory;
	}

	// Fail if basename (and therefor dirname) is NULL.
	if (path.getBasename() == NULL) {
		return CFinder::ERROR_DIRECTORY_CANTSPLIT;
	}

	// Attempt to get directory attributes.
	DWORD dattr_err;
	DWORD dattr = path.getAttributes(dattr_err);

	// Try again if it failed and the path is not exact.
	if (!path.isExact() && dattr == INVALID_FILE_ATTRIBUTES) {
		path.setExact(true);
		dattr = path.getAttributes(dattr_err);
	}

	if (dattr == INVALID_FILE_ATTRIBUTES) {
		switch (dattr_err) {
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

	outputHeader();

	processDirectory(NULL, 1);

	return 0;
}

void CFinder::outputHeader() {
	SYSTEMTIME now;
	GetSystemTime(&now);

	// Output the header.
	cout << "## v2 " << (int)delim << " " << (int)dirSeparator << " ";
	
	// Output date/time
	printf(
		"%04d-%02d-%02d %02d:%02d:%02d",
		now.wYear, now.wMonth, now.wDay,
		now.wHour, now.wMinute, now.wSecond
	);

	char* escapeChar = CFinder::UnicodeToUTF8(path.getEscapeChar());

	cout << " escaped:" << (int)(escapeChar[0])
	     << " datetimeformat:timestamp";

	delete[] escapeChar;

	char* dirnameUTF8 = CFinder::UnicodeToUTF8(path.getDirnameOut());
	char* basenameUTF8 = CFinder::UnicodeToUTF8(path.getBasenameOut());

	// Flip the dirname and basename if the basename is empty.
	// This can happen if the path is "c:\\"
	if (strlen(basenameUTF8) == 0) {
		char* tmp = basenameUTF8;
		basenameUTF8 = dirnameUTF8;
		dirnameUTF8 = tmp;
		tmp = NULL;
	}
	
	cout << " dirname:" << dirnameUTF8;
	cout << " basename:" << basenameUTF8;
	
	delete[] dirnameUTF8;
	delete[] basenameUTF8;
	
	cout << endl;
}

void CFinder::processDirectory(_TCHAR* name, int depth) {
	if (name != NULL) {
		path.push(name);
	}

	WIN32_FIND_DATA findData;
	HANDLE hFind = INVALID_HANDLE_VALUE;
	
	path.push(_T("*"));
	hFind = FindFirstFile(path.getPath(), &findData);
	path.pop();

	if (hFind == INVALID_HANDLE_VALUE) {

		DWORD lastError = GetLastError();
		if (!path.isExact() && lastError == ERROR_PATH_NOT_FOUND) {
			// Try again with exact path, if path was not found and not already exact.
			path.setExact(true);
			processDirectory(NULL, depth);
			path.setExact(false);
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

			char* fullPathUTF8 = CFinder::UnicodeToUTF8(path.getPath());
			cerr << "): " << fullPathUTF8 << endl;
			delete[] fullPathUTF8;

			outputError("OPENDIR_FAIL");
		}
	}
	else {
		// Loop through the files.
		do {
			if (_tcscmp(findData.cFileName, _T(".")) != 0
				&& _tcscmp(findData.cFileName, _T("..")) != 0) {
			
				processEntry(depth, findData);
			}
		} while (FindNextFile(hFind, &findData));
	}

	if (name != NULL) {
		path.pop();
	}
}

void CFinder::processEntry(int depth, WIN32_FIND_DATA findData) {
	char type;

	if (findData.dwFileAttributes & FILE_ATTRIBUTE_REPARSE_POINT) {
		if (this->followLinks && findData.dwFileAttributes & FILE_ATTRIBUTE_DIRECTORY) {
			type = 'd';
		}
		else {
			type = 'l';
		}
	}
	else if (findData.dwFileAttributes & FILE_ATTRIBUTE_DIRECTORY) {
		type = 'd';
	}
	else {
		type = 'f';
	}

	outputEntry(type, depth, findData);
	
	// List contents if entry is a directory.
	if (type == 'd') {
		processDirectory(findData.cFileName, depth + 1);
	}
}

void CFinder::outputEntry(char type, int depth, WIN32_FIND_DATA findData) {
	
	// Add the file name to the out path.
	path.push(findData.cFileName);
	
	char* utf8Name = CFinder::UnicodeToUTF8(path.getOutPath());
	
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

	// Output file name.
	cout << utf8Name << endl;
	
	delete[] utf8Name;

	path.pop();
}

void CFinder::outputError(char* code) {
	char* pathExtUTF8 = CFinder::UnicodeToUTF8(path.getOutPath());

	cout << "!";
	fwrite(&delim, 1, 1, stdout);
	
	cout << code;
	fwrite(&delim, 1, 1, stdout);
	
	cout << pathExtUTF8 << endl;

	delete[] pathExtUTF8;
}

char* CFinder::UnicodeToUTF8(const _TCHAR cunicode) {
	_TCHAR unicode[2] = { cunicode, _T('\0') };
	return CFinder::UnicodeToUTF8(unicode);
}

char* CFinder::UnicodeToUTF8(const _TCHAR* unicode) {
	int bufferSize = 0;
	
	if (_ISUNICODE)
		bufferSize = WideCharToMultiByte(CP_UTF8, 0, unicode, -1, NULL, 0, NULL, NULL);
	else
		bufferSize = _tcslen(unicode);

	char* utf8 = new char[bufferSize];
	
	if (_ISUNICODE)
		WideCharToMultiByte(CP_UTF8, 0, unicode, -1, utf8, bufferSize, NULL, NULL);
	else
		strcpy_s(utf8, bufferSize + 1, (const char*)unicode);

	return utf8;
}

bool CFinder::SplitPath(_TCHAR* path, SPLIT_PATH_DATA& data) {
	if (!_tsplitpath_s(path, data.drive, _TMAX_DRIVE, data.dir, _TMAX_DIR, data.fname, _TMAX_FNAME, data.ext, _TMAX_EXT))
		return false;

	// Combine drive and dir to make dirname.
	_TSTRING dirnameS(data.drive);
	dirnameS += _TSTRING(data.dir);
	
	// Trim trailing slash (/)
	if (dirnameS.size() != 0) {
		_TSTRING::size_type lastNotSlash = dirnameS.find_last_not_of(_T("\\"));
		if (lastNotSlash != -1 && lastNotSlash + 1 < dirnameS.size()) {
			dirnameS.erase(lastNotSlash + 1);
		}
	}

	// Copy dirname to the struct
	data.dirname = new _TCHAR[dirnameS.size() + 1];
	_tcscpy_s(data.dirname, dirnameS.size() + 1, dirnameS.c_str());

	// Combine fname and ext to make the basename.
	_TSTRING basenameS(data.fname);
	basenameS += _TSTRING(data.ext);
	
	// Copy basename to the struct
	data.basename = new _TCHAR[basenameS.size() + 1];
	_tcscpy_s(data.basename, basenameS.size() + 1, basenameS.c_str());

	return true;
}

CFinder::SPLIT_PATH_DATA::~SPLIT_PATH_DATA() {
	delete[] basename;
	delete[] dirname;
}

void CFinder::replacePathDS(_TCHAR* path) {
	// Replace directory separators if a different one was specified.
	if (_tdirSeparator != _T('\\')) {
		for (size_t i = 0; i < _tcslen(path); i++) {
			if (path[i] == _T('\\')) {
				path[i] = _tdirSeparator;
			}
		}
	}
}

void CFinder::MakePathExtendedLength(_TCHAR* path, _TCHAR* extended, int maxLength) {
	_TSTRING pathS(path);
		
	// UNC path
	if (pathS.compare(0, 2, _T("\\\\")) == 0) {
		pathS.insert(2, _T("?\\UNC\\"));
	}
	else {
		pathS.insert(0, _T("\\\\?\\"));
	}

	_tcscpy_s(extended, maxLength, pathS.c_str());
}