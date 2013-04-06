[Disk Usage Reports](http://diskusagereports.com/)
==================================================

Even in a world of cheap storage, sometimes disk usage means a lot.
Backups can take forever and unnecessarily large files can eat up bandwidth.
Disk Usage Reports provides you with **rich web-based usage reports** to help keep things under control.

Find out more at http://diskusagereports.com!


Demo
---------------

Visit http://diskusagereports.com/demo/ for a live demo.


Quick Start
---------------

1.  Download the latest version at http://diskusagereports.com/download.html

2.  Unzip the files into your Web server's public directory:
    
    Linux: `/usr/local/apache/htdocs` or `/var/www/html` (check your httpd.conf)
    
    Windows: `C:\Inetpub\wwwroot`

3.  If your web server executes PHP scripts, you must either secure the `scripts` directory so
    it is not publicly accessible, or move the it to a location on your server that is not
    publicly accessible.

4.  Open a console/terminal window and change directory
    (e.g. `cd /usr/local/apache/htdocs/diskusagereports`) to the installation directory.

5.  Make sure PHP is available by executing: `php -v`

6.  Execute one of the following, changing `path/to/directory` to something else:
    
    Linux: `bash scripts/find.sh path/to/directory | php scripts/process.php data/myreport`
    
    Windows: `scripts\find.exe path\to\directory | php scripts\process.php data\myreport`
    
    Others: `php scripts/find.php path/to/directory | php scripts/process.php data/myreport`

7.  Open your browser and visit the following (changing anything before the `?` as necessary):

    `http://localhost/diskusagereports/?myreport`

8.  You should be viewing a usage report! If not, get help at http://diskusagereports.com/help/

Getting Help
---------------

Full documentation is available at http://diskusagereports.com/docs/.

To get assistance, please contact me at help@diskusagereports.com.

You may also submit issues at https://github.com/amekkawi/diskusagereports/issues (requires free GitHub account).


License
---------------

Copyright &copy; 2011-2012 André Mekkawi <license@diskusagereports.com>

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.


Image Credits
---------------

Images: folder-closed.png, folder-open.png, folder-large.png  
By: Fugue Icons by Yusuke Kamiyamane  
Source: http://p.yusukekamiyamane.com/

Image: title-icon2.png, info.png, close.png, error22x22.png, error32x32.png, titleicon2.png, favicon.png  
By: Nuvola Icon Theme by David Vignoni  
Source: http://www.icon-king.com
