#include "StdAfx.h"
#include "Finder.h"
#include <sys/stat.h>
#include <algorithm>

#define buffLength 4000

using namespace std;

CFinder::CFinder(void) {
	ds = _T('\\');
	delim = _T('\0');
}

CFinder::~CFinder(void) {

}

void CFinder::setDelim(_TCHAR delim) {
	this->delim = delim;
}

void CFinder::setDS(_TCHAR ds) {
	this->ds = ds;
}

int CFinder::run(_TCHAR* directory) {
	//struct _stat dirstat;

	// Determine the real (absolute) path.
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
	
	/*// Attempt to stat the directory. Return error if stat fails.
	if (_tstat(directory, &dirstat) != 0) {
		//delete[] directory;

		switch (errno) {
			case ENOENT:
				return CFinder::ERROR_DIRECTORY_NOTFOUND;
			case EACCES:
				return CFinder::ERROR_DIRECTORY_ACESSDENIED;
			default:
				return CFinder::ERROR_DIRECTORY_STAT;
		}
	}

	if (!(dirstat.st_mode & _S_IFDIR)) {
		return CFinder::ERROR_DIRECTORY_NOTFOUND;
	}*/

	/*fwrite($out, implode($this->_delim, array(
			'#',
			$this->_ds,
			str_replace(DIRECTORY_SEPARATOR, $this->_ds, $dirname),
			str_replace(DIRECTORY_SEPARATOR, $this->_ds, $basename),
			date('Y-m-d H:i:s')
		)) . "\n");*/

	SYSTEMTIME now;
	GetSystemTime(&now);

	cout << "#";
	fwrite(&delim, 1, 1, stdout);

	// Output date/time
	printf(
		"%04d-%02d-%02d %02d:%02d:%02d",
		now.wYear, now.wMonth, now.wDay,
		now.wHour, now.wMinute, now.wSecond
	);
	fwrite(&delim, 1, 1, stdout);
	

	cout << endl;

	processDirectory(directory, _T(""), 1);

	//delete[] directory;
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
		_TCHAR* subPathExt = createPath(_T(""), pathExt, findData.cFileName);
		processDirectory(rootPath, subPathExt, depth + 1);
		delete[] subPathExt;
	}
}

void CFinder::outputEntry(char type, _TCHAR* pathExt, int depth, WIN32_FIND_DATA findData) {
	_TCHAR* path = createPath(_T(""), pathExt, findData.cFileName);

	// Replace directory separators if a different one was specified.
	if (ds != _T('\\')) {
		for (size_t i = 0; i < _tcslen(path); i++) {
			if (path[i] == _T('\\')) {
				path[i] = ds;
			}
		}
	}

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

void CFinder::SplitPath(_TCHAR* path, SPLIT_PATH_DATA* data) {
	/*_TCHAR drive[_MAX_DRIVE];
	_TCHAR dir[_MAX_DIR];
	_TCHAR fname[_MAX_FNAME];
	_TCHAR ext[_MAX_EXT];*/
	_tsplitpath_s(path, data->drive, _MAX_DRIVE, data->dir, _MAX_DIR, data->fname, _MAX_FNAME, data->ext, _MAX_EXT);

	_tstring dirnameS(data->drive);
	dirnameS += _tstring(data->dir);
	
	// Trim trailing slash (/)
	if (dirnameS.size() != 0) {
		_tstring::size_type lastNotSlash = dirnameS.find_last_not_of(_T("\\"));
		if (lastNotSlash != -1 && lastNotSlash + 1 < dirnameS.size()) {
			dirnameS.erase(lastNotSlash + 1);
		}
	}

	data->dirname = new _TCHAR[dirnameS.size() + 1];
	_tcscpy_s(data->dirname, dirnameS.size() + 1, dirnameS.c_str());

	_tstring basenameS(data->fname);
	basenameS += _tstring(data->ext);
	
	data->basename = new _TCHAR[basenameS.size() + 1];
	_tcscpy_s(data->basename, basenameS.size() + 1, basenameS.c_str());
}

CFinder::SPLIT_PATH_DATA::~SPLIT_PATH_DATA() {
	delete[] basename;
	delete[] dirname;
}