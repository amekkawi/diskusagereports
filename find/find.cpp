// find.cpp : Defines the entry point for the console application.
//

#include "stdafx.h"
#include "Finder.h"

using namespace std;

int _tmain(int argc, _TCHAR* argv[]) {
	
	_TCHAR* directory = NULL;
	CFinder finder;

	for (int i = 1; i < argc; i++) {
		if (_tcscmp(argv[i], _T("-?")) == 0
			|| _tcscmp(argv[i], _T("-h")) == 0
			|| _tcscmp(argv[i], _T("/?")) == 0
			|| _tcscmp(argv[i], _T("/h")) == 0) {
			
			// output syntax
			return 0;
		}
		else if (_tcscmp(argv[i], _T("-d")) == 0) {
			if (++i == argc) {
				cerr << "-d must be followed by an argument." << endl;
				// output syntax
				return 1;
			}

			if (_tcslen(argv[i]) != 1) {
				cerr << "The argument after -d must be one character long." << endl;
				// output syntax
				return 1;
			}

			char* delimUTF8 = CFinder::UnicodeToUTF8(argv[i]);
			int delimSize = strlen(delimUTF8);
			delete[] delimUTF8;

			if (delimSize != 1) {
				cerr << "The argument after -d cannot be a multi-byte character." << endl;
				// output syntax
				return 1;
			}

			finder.setDelim(argv[i][0]);
		}
		else if (_tcscmp(argv[i], _T("-ds")) == 0) {
			if (++i == argc) {
				cerr << "-ds must be followed by an argument" << endl;
				// output syntax
				return 1;
			}

			if (_tcslen(argv[i]) != 1) {
				cerr << "The argument after -ds must be one character long." << endl;
				// output syntax
				return 1;
			}

			char* dsUTF8 = CFinder::UnicodeToUTF8(argv[i]);
			int dsSize = strlen(dsUTF8);
			delete[] dsUTF8;

			if (dsSize != 1) {
				cerr << "The argument after -ds cannot be a multi-byte character." << endl;
				// output syntax
				return 1;
			}

			finder.setDS(argv[i][0]);
		}
		else {
			if (_tcslen(argv[i]) > MAX_PATH) {
				cerr << "The <directory> argument cannot be longer than " << MAX_PATH << " characters." << endl;
				// output syntax
				return 1;
			}

			directory = argv[i];
		}
	}

	if (directory == 0) {
		cerr << "The <directory> argument is required." << endl;
		// output syntax
		return 1;
	}

	directory = L"c:\\test.out";

	int ret = finder.run(directory);

	switch (ret) {
		case CFinder::ERROR_DIRECTORY_NOTFOUND:
			cerr << "The <directory> does not exist or is not a directory." << endl;
			break;
		case CFinder::ERROR_DIRECTORY_STAT:
		case CFinder::ERROR_DIRECTORY_ACESSDENIED:
			cerr << "Failed to retrieve info (via GetFileAttributes) on <directory>. You may not have access to the directory or its parent directories." << endl;
			break;
	}

	return ret;
}

