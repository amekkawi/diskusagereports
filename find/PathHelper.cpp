#include "StdAfx.h"
#include "PathHelper.h"

using namespace std;

/* Constructors */

CPathHelper::CPathHelper() {
	Init(_T('\\'), _T(""), NULL);
}

CPathHelper::CPathHelper(_TCHAR separator) {
	Init(separator, _T(""), NULL);
}

CPathHelper::CPathHelper(_TCHAR separator, _TCHAR* path) {
	Init(separator, path, NULL);
}

CPathHelper::CPathHelper(_TCHAR separator, _TCHAR* path, _TCHAR escapeChar) {
	Init(separator, path, escapeChar);
}

CPathHelper::~CPathHelper(void) {
	if (dirname != NULL) delete[] dirname;
	if (dirnameOut != NULL) delete[] dirnameOut;

	if (basename != NULL) delete[] basename;
	if (basenameOut != NULL) delete[] basenameOut;
}

/* Constructor Init */

void CPathHelper::Init(_TCHAR separator, _TCHAR* path, _TCHAR escapeChar) {
	dirname = NULL;
	dirnameOut = NULL;
	basename = NULL;
	basenameOut = NULL;

	setSeparator(separator);
	setPath(path);
	setEscapeChar(escapeChar);
}

/* Public Is/Set/Get Functions */

bool CPathHelper::isExact() {
	return prefix != PREFIX_NONE;
}

void CPathHelper::setExact(bool exact) {
	if (exact && prefix == PREFIX_NONE) {
		prefix = CPathHelper::MakeExtendedLength(path);
	}
	else if (!exact && prefix == PREFIX_REG) {
		path.erase(0, 4);
		prefix = PREFIX_NONE;
	}
	else if (!exact && prefix == PREFIX_UNC) {
		path.erase(2, 6);
		prefix = PREFIX_NONE;
	}
}

_TCHAR CPathHelper::getSeparator() {
	return separator;
}

void CPathHelper::setSeparator(_TCHAR separator) {
	this->separator = separator;
}

_TCHAR CPathHelper::getEscapeChar() {
	return escapeChar;
}

void CPathHelper::setEscapeChar(_TCHAR escapeChar) {
	this->escapeChar = escapeChar;
}

void CPathHelper::setPath(_TSTRING path) {
	setPath(path.c_str());
}

void CPathHelper::setPath(const _TCHAR* path) {
	this->path = path;
	outPath = _T("");

	pathLengths.empty();
	outPathLengths.empty();

	trimSlashes();
	splitPath();

	prefix = DetectPrefixType(this->path);
}

const _TCHAR* CPathHelper::getPath() {
	return path.c_str();
}

const _TSTRING CPathHelper::getPathS() {
	return path;
}

const _TCHAR* CPathHelper::getOutPath() {
	return outPath.c_str();
}

const _TSTRING CPathHelper::getOutPathS() {
	return outPath;
}

const _TCHAR* CPathHelper::getDirname() {
	return dirname;
}

const _TCHAR* CPathHelper::getDirnameOut() {
	return dirnameOut;
}

const _TCHAR* CPathHelper::getBasename() {
	return basename;
}

const _TCHAR* CPathHelper::getBasenameOut() {
	return basenameOut;
}

size_t CPathHelper::length() {
	return path.length();
}

/* Public Functions */

DWORD CPathHelper::getAttributes(DWORD& errorCode) {
	DWORD dattr = GetFileAttributes(getPath());
	if (dattr == INVALID_FILE_ATTRIBUTES) {
		errorCode = GetLastError();
	}
	return dattr;
}

void CPathHelper::push(_TCHAR* part) {
	// Record the current path lengths.
	pathLengths.push(path.length() - getPrefixLength());
	outPathLengths.push(outPath.length() - getPrefixLength());

	// Add a separator to the path only if it doesn't already end with one.
	if (path.length() > 0 && path[path.length() - 1] != _T('\\')) {
		path += _T('\\');
	}

	path += part;

	// Add a separator to the outPath only if it is at least one character long.
	if (outPath.length() > 0) {
		outPath += separator;
	}
	
	_TSTRING escaped(part);
	
	// Escape, only if the escapeChar has been set.
	if (escapeChar != NULL)
		EscapePathSegment(escaped, escapeChar, false);

	outPath += escaped;
}

void CPathHelper::pop() {
	// Record the current path lengths.
	size_t pathLength = pathLengths.top() + getPrefixLength();
	size_t outPathLength = outPathLengths.top() + getPrefixLength();

	pathLengths.pop();
	outPathLengths.pop();

	path.erase(pathLength);
	outPath.erase(outPathLength);
}

/* Public Static Functions */

int CPathHelper::DetectPrefixType(_TCHAR* path) {
	_TSTRING pathS(path);
	return CPathHelper::DetectPrefixType(pathS);
}

int CPathHelper::DetectPrefixType(_TSTRING& path) {
	// UNC path
	if (path.compare(0, 8, _T("\\\\?\\UNC\\")) == 0) {
		return PREFIX_UNC;
	}
	else if (path.compare(0, 4, _T("\\\\?\\")) == 0) {
		return PREFIX_REG;
	}
	else {
		return PREFIX_NONE;
	}
}

int CPathHelper::MakeExtendedLength(_TCHAR* path, _TCHAR* extended, size_t maxLength) {
	_TSTRING pathS(path);
	int ret = CPathHelper::MakeExtendedLength(pathS);
	_tcscpy_s(extended, maxLength, pathS.c_str());
	return ret;
}

int CPathHelper::MakeExtendedLength(_TSTRING& path) {
	// UNC path
	if (path.compare(0, 2, _T("\\\\")) == 0) {
		path.insert(2, _T("?\\UNC\\"));
		return PREFIX_UNC;
	}
	else {
		path.insert(0, _T("\\\\?\\"));
		return PREFIX_REG;
	}
}

void CPathHelper::ReplaceAll(_TSTRING& str, _TCHAR from, _TCHAR to) {
	for (size_t i = 0; i < str.size(); i++) {
		if (str[i] == from) {
			str[i] = to;
		}
	}
}

void CPathHelper::ReplaceAll(_TSTRING& str, _TSTRING& from, _TSTRING& to) {
	size_t i = 0;
	while ((i = str.find(from, i)) != _TSTRING::npos) {
        str.replace(i, from.size(), to);
        i += to.size();
    }
}

void CPathHelper::EscapePathSegment(_TSTRING& str, _TCHAR escapeChar, bool escapeSpace) {
	_TSTRING escapeCharS(1, escapeChar);

	ReplaceAll(str, escapeCharS, escapeCharS + escapeCharS);
	ReplaceAll(str, _TSTRING(_T("\n")), escapeCharS + _T("n"));

	if (escapeSpace)
		ReplaceAll(str, _TSTRING(_T(" ")), escapeCharS + _T(" "));
}

/* Public Overloaded Operators */

CPathHelper& CPathHelper::operator = (const _TSTRING& str) {
	setPath(str);
	return *this;
}
CPathHelper& CPathHelper::operator = (const _TCHAR* chrs) {
	setPath(chrs);
	return *this;
}

/* Private Functions */

size_t CPathHelper::getPrefixLength() {
	if (prefix == PREFIX_UNC)
		return 6;
	else if (prefix == PREFIX_REG)
		return 4;
	else
		return 0;
}

void CPathHelper::splitPath() {
	if (dirname != NULL) delete[] dirname;
	if (dirnameOut != NULL) delete[] dirnameOut;
	dirname = NULL;
	dirnameOut = NULL;

	if (basename != NULL) delete[] basename;
	if (basenameOut != NULL) delete[] basenameOut;
	basename = NULL;
	basenameOut = NULL;

	_TCHAR drive[_TMAX_DRIVE];
	_TCHAR dir[_TMAX_DIR];
	_TCHAR fname[_TMAX_FNAME];
	_TCHAR ext[_TMAX_EXT];

	// Attempt to split the path.
	if (_tsplitpath_s(path.c_str(), drive, _TMAX_DRIVE, dir, _TMAX_DIR, fname, _TMAX_FNAME, ext, _TMAX_EXT) == 0) {

		// Combine drive and dir to make dirname.
		_TSTRING dirnameS(drive);
		dirnameS += dir;
		
		// Trim trailing slash (/)
		if (dirnameS.size() > 1) {
			_TSTRING::size_type lastNotSlash = dirnameS.find_last_not_of(_T("\\"));
			if (lastNotSlash != -1 && lastNotSlash + 1 < dirnameS.size()) {
				dirnameS.erase(lastNotSlash + 1);
			}
		}

		dirname = new _TCHAR[dirnameS.size() + 1];
		_tcscpy_s(dirname, dirnameS.size() + 1, dirnameS.c_str());

		// Replace directory separators if a different one was specified.
		if (separator != _T('\\')) {
			ReplaceAll(dirnameS, _T('\\'), separator);
		}

		// Escape, only if the escapeChar has been set.
		EscapePathSegment(dirnameS, _T('\\'), true);

		dirnameOut = new _TCHAR[dirnameS.size() + 1];
		_tcscpy_s(dirnameOut, dirnameS.size() + 1, dirnameS.c_str());

		// Combine fname and ext to make the basename.
		_TSTRING basenameS(fname);
		basenameS += ext;
	
		basename = new _TCHAR[basenameS.size() + 1];
		_tcscpy_s(basename, basenameS.size() + 1, basenameS.c_str());

		// Escape, only if the escapeChar has been set.
		EscapePathSegment(basenameS, _T('\\'), true);
	
		basenameOut = new _TCHAR[basenameS.size() + 1];
		_tcscpy_s(basenameOut, basenameS.size() + 1, basenameS.c_str());
	}
}


void CPathHelper::trimSlashes() {
	// Remove trailing slashes (unless the slash is preceeded by a colon).
	if (path.size() > 1) {
		size_t last = path.find_last_not_of(_T('\\'));
		if (last > 0 && last < path.size() - 1 && path[last] != _T(':')) {
			path.erase(last + 1);
		}
	}
}