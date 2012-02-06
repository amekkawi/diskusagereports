// stdafx.h : include file for standard system include files,
// or project specific include files that are used frequently, but
// are changed infrequently
//

#pragma once

#include "targetver.h"

#include <stdio.h>
#include <tchar.h>
#include <iostream>
#include <string>
#include <windows.h>

#ifndef _tstring
	#ifdef _UNICODE
		#define _tstring wstring
	#else
		#define _tstring string
	#endif
#endif

// TODO: reference additional headers your program requires here
