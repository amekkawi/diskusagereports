#include "StdAfx.h"
#include "Finder.h"
#include <sys/stat.h>

#define buffLength 4000

using namespace std;

CFinder::CFinder(void) {
	ds = '\\';
	delim = '\0';
}

CFinder::~CFinder(void) {

}

void CFinder::setDelim(char delim) {
	this->delim = delim;
}

void CFinder::setDS(char ds) {
	this->ds = ds;
}

int CFinder::run(_TCHAR* directory) {
	//char* msg;
	struct _stat dirstat;

	// Convert directory path to string.
	_tstring sdir (directory);
	
	// Trim trailing slashes.
	if (sdir.size() != 0) {
		_tstring::size_type lastNotSlash = sdir.find_last_not_of(_T("\\"));
		if (lastNotSlash != -1 && lastNotSlash + 1 < sdir.size()) {
			sdir.erase(lastNotSlash + 1);
		}
	}
	
	// Convert the directory path back.
	directory = new _TCHAR[sdir.size() + 1];
	_tcscpy_s(directory, sdir.size() + 1, sdir.c_str()); 

	/*_TCHAR buff[4096];
	_TCHAR** lppPart = { NULL };
	DWORD ret = GetFullPathName(_T("\\test\\"), 4096, buff, lppPart);*/

	// Attempt to stat the directory. Return error if stat fails.
	if (_tstat(_T("c:\\test"), &dirstat) != 0) {
		delete[] directory;

		switch (errno) {
			case ENOENT:
				return CFinder::ERROR_DIRECTORY_NOTFOUND;
			case EACCES:
				return CFinder::ERROR_DIRECTORY_ACESSDENIED;
			default:
				return CFinder::ERROR_DIRECTORY_STAT;
				//msg = new char[100];
				//_strerror_s(msg, 100, "Error checking <directory>: ");
		}

		//cerr << msg << endl;
		//delete[] msg;
	}

	processDirectory(directory, _T(""), 1);

	delete[] directory;
	return 0;
}

void CFinder::processDirectory(_TCHAR* rootPath, _TCHAR* pathExt, int depth) {
	_TCHAR* fullPath = createPath(rootPath, pathExt, _T("*"));

	WIN32_FIND_DATA findData;
	HANDLE hFind = INVALID_HANDLE_VALUE;
	
	hFind = FindFirstFile(fullPath, &findData);
	if (hFind == INVALID_HANDLE_VALUE) {
		printf ("FindFirstFile failed (%d)\n", GetLastError());
	}
	else {
		do {
			if (_tcscmp(findData.cFileName, _T(".")) != 0
				&& _tcscmp(findData.cFileName, _T("..")) != 0) {
			
				processEntry(rootPath, pathExt, depth, findData);
			}
		} while (FindNextFile(hFind, &findData));
	}

	delete[] fullPath;
}

void CFinder::processEntry(_TCHAR* rootPath, _TCHAR* pathExt, int depth, WIN32_FIND_DATA findData) {
	char type;

	if (findData.dwFileAttributes & FILE_ATTRIBUTE_DIRECTORY) {
		type = 'd';
	}
	else if (findData.dwFileAttributes & FILE_ATTRIBUTE_REPARSE_POINT) {
		type = 'l';
	}
	else {
		type = 'f';
	}

	outputEntry(type, pathExt, depth, findData);

	
	_TCHAR* fullPath = createPath(rootPath, pathExt, _T(""));
	if (findData.dwFileAttributes & FILE_ATTRIBUTE_DIRECTORY) {
		_TCHAR* subPathExt = createPath(_T(""), pathExt, findData.cFileName);
		processDirectory(rootPath, subPathExt, depth + 1);
	}

	delete[] fullPath;
}

void CFinder::outputEntry(char type, _TCHAR* pathExt, int depth, WIN32_FIND_DATA findData) {
	_TCHAR* path = createPath(_T(""), pathExt, findData.cFileName);
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
	delete[] path;
}

_TCHAR* CFinder::createPath(_TCHAR* rootPath, _TCHAR* pathExt, _TCHAR* entry) {
	_tstring fullPathS(rootPath);

	if (_tcslen(pathExt) != 0) {
		fullPathS += (fullPathS.size() == 0 ? _T("") : _T("\\")) + _tstring(pathExt);
	}

	if (_tcslen(entry) != 0) {
		fullPathS += (fullPathS.size() == 0 ? _T("") : _T("\\")) + _tstring(entry);
	}
	
	_TCHAR* fullPath = new _TCHAR[fullPathS.size() + 1];
	_tcscpy_s(fullPath, fullPathS.size() + 1, fullPathS.c_str());

	return fullPath;
}

char* CFinder::UnicodeToUTF8(_TCHAR* unicode) {
	int bufferSize = WideCharToMultiByte(CP_UTF8, 0, unicode, -1, NULL, 0, NULL, NULL);
	char* utf8 = new char[bufferSize]; 
	WideCharToMultiByte(CP_UTF8, 0, unicode, -1, utf8, bufferSize, NULL, NULL);
	return utf8;
}
