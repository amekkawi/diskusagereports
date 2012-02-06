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

			char* delim = CFinder::UnicodeToUTF8(argv[i]);
			if (strlen(delim) != 1) {
				cerr << "The argument after -d must be one character long." << endl;
				// output syntax

				delete[] delim;
				return 1;
			}

			finder.setDelim(delim[0]);
		}
		else if (_tcscmp(argv[i], _T("-ds")) == 0) {
			if (++i == argc) {
				cerr << "-ds must be followed by an argument" << endl;
				// output syntax
				return 1;
			}
			
			char* ds = CFinder::UnicodeToUTF8(argv[i]);
			if (strlen(ds) != 1) {
				cerr << "The argument after -ds must be one character long." << endl;
				// output syntax
				return 1;
			}
			finder.setDS(ds[0]);
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

