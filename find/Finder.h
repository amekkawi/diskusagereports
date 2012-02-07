#pragma once

class CFinder
{
private:
	_TCHAR delim;
	_TCHAR ds;

	void processDirectory(_TCHAR* rootPath, _TCHAR* pathExt, int depth);
	void processEntry(_TCHAR* rootPath, _TCHAR* pathExt, int depth, WIN32_FIND_DATA findData);
	void outputEntry(char type, _TCHAR* pathExt, int depth, WIN32_FIND_DATA findData);
	_TCHAR* createPath(_TCHAR* rootPath, _TCHAR* pathExt, _TCHAR* entry);

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
	~CFinder(void);

	void setDelim(_TCHAR delim);
	void setDS(_TCHAR delim);
	int run(_TCHAR* directory);

	static char* UnicodeToUTF8(_TCHAR* unicode);
	static void CFinder::SplitPath(_TCHAR* path, SPLIT_PATH_DATA* data);
};

