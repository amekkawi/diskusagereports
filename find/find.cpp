// find.cpp : Defines the entry point for the console application.
//

#include "stdafx.h"
#include "Finder.h"

using namespace std;

int _tmain(int argc, _TCHAR* argv[]) {
	// Set timezone?

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
			else if (sizeof(argv[i]) != 1) {
				cerr << "The argument after -d be only one character long." << endl;
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
			else if (sizeof(argv[i]) != 1) {
				cerr << "The argument after -ds be only one character long." << endl;
				// output syntax
				return 1;
			}
			finder.setDS(argv[i][0]);
		}
		else {
			directory = argv[i];
		}
	}

	if (directory == 0) {
		cerr << "The <directory> argument is required." << endl;
		// output syntax
		return 1;
	}
	
	finder.run(directory);

	return 0;
}

