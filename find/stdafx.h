// stdafx.h : include file for standard system include files,
// or project specific include files that are used frequently, but
// are changed infrequently
//

#pragma once

#include "targetver.h"

#include <stdio.h>
#include <iostream>
#include <tchar.h>
#include <string>
#include <windows.h>

#ifndef _TSTRING
	#ifdef _UNICODE
		#define _TSTRING wstring
	#else
		#define _TSTRING string
	#endif
#endif

#ifdef _UNICODE
	#define _ISUNICODE true
	#define _TMAX_PATH 32767

	#define _TMAX_DRIVE 32767
	#define _TMAX_DIR 32767
	#define _TMAX_FNAME 32767
	#define _TMAX_EXT 32767
#else
	#define _ISUNICODE false
	#define _TMAX_PATH MAX_PATH

	#define _TMAX_DRIVE _MAX_DRIVE
	#define _TMAX_DIR _MAX_DIR
	#define _TMAX_FNAME _MAX_FNAME
	#define _TMAX_EXT _MAX_EXT
#endif

// TODO: reference additional headers your program requires here
