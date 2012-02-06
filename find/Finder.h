#pragma once

class CFinder
{
private:
	char delim;
	char ds;

	void processDirectory(_TCHAR* rootPath, _TCHAR* pathExt, int depth);
	void processEntry(_TCHAR* rootPath, _TCHAR* pathExt, int depth, WIN32_FIND_DATA findData);
	void outputEntry(char type, _TCHAR* pathExt, int depth, WIN32_FIND_DATA findData);
	_TCHAR* createPath(_TCHAR* rootPath, _TCHAR* pathExt, _TCHAR* entry);

public:
	const static int ERROR_DIRECTORY_NOTFOUND = 1;
	const static int ERROR_DIRECTORY_ACESSDENIED = 2;
	const static int ERROR_DIRECTORY_STAT = 3;

	CFinder(void);
	~CFinder(void);

	void setDelim(char delim);
	void setDS(char delim);
	int run(_TCHAR* directory);

	static char* UnicodeToUTF8(_TCHAR* unicode);
};

