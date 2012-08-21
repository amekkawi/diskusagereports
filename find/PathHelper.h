/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

#pragma once

#include "StdAfx.h"
#include <stack>

using namespace std;

class CPathHelper {

public:
	/* Flags */

	const static int PREFIX_NONE = 0;
	const static int PREFIX_REG = 1;
	const static int PREFIX_UNC = 2;

	/* Constructors */

	CPathHelper();
	CPathHelper(_TCHAR separator);
	CPathHelper(_TCHAR separator, _TCHAR* path);
	CPathHelper(_TCHAR separator, _TCHAR* path, _TCHAR escapeChar);
	~CPathHelper(void);
	
	/* Public Is/Set/Get Functions */

	bool isExact();
	void setExact(bool exact);

	_TCHAR getSeparator();
	void setSeparator(_TCHAR separator);

	_TCHAR getEscapeChar();
	void setEscapeChar(_TCHAR escapeChar);

	void setPath(_TSTRING path);
	void setPath(const _TCHAR* path);

	const _TCHAR* getPath();
	const _TSTRING getPathS();
	const _TCHAR* getOutPath();
	const _TSTRING getOutPathS();

	const _TCHAR* getDirname();
	const _TCHAR* getDirnameOut();
	const _TCHAR* getBasename();
	const _TCHAR* getBasenameOut();

	size_t length();
	
	/* Public Functions */

	DWORD getAttributes(DWORD& errorCode);
	void push(_TCHAR* part);
	void pop();

	/* Public Static Functions */

	static int DetectPrefixType(_TSTRING& path);
	static int DetectPrefixType(_TCHAR* path);
	static int MakeExtendedLength(_TCHAR* path, _TCHAR* extended, size_t maxLength);
	static int MakeExtendedLength(_TSTRING& path);

	static void ReplaceAll(_TSTRING& str, _TCHAR from, _TCHAR to);
	static void ReplaceAll(_TSTRING& str, _TSTRING& from, _TSTRING& to);
	static void EscapePathSegment(_TSTRING& str, _TCHAR escapeChar, bool escapeSpace);

	/* Public Overloaded Operators */

	CPathHelper& operator = (const _TSTRING& str);
	CPathHelper& operator = (const _TCHAR* chrs);
	
private:
	/* Constructor Init */

	void Init(_TCHAR separator, _TCHAR* path, _TCHAR escapeChar);

	/* Private Members */

	_TSTRING path;
	_TSTRING outPath;
	
	stack<size_t> pathLengths;
	stack<size_t> outPathLengths;

	// Set to one of the PREFIX_ flags.
	int prefix;

	// Directory separator for the outPath.
	_TCHAR separator;

	// Escape character for the outPath.
	_TCHAR escapeChar;

	_TCHAR* dirname;
	_TCHAR* dirnameOut;
	_TCHAR* basename;
	_TCHAR* basenameOut;

	/* Private Functions */

	size_t getPrefixLength();
	void splitPath();
	void trimSlashes();
};
