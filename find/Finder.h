#pragma once
class CFinder
{
private:
	_TCHAR delim;
	_TCHAR ds;

public:
	CFinder(void);
	~CFinder(void);

	void setDelim(_TCHAR delim);
	void setDS(_TCHAR delim);
};

