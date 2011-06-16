/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereport.sf.net/license.html
 */

;(function($) {

var _scrollBarSize = null;

$.scrollBarSize = function() {
	if (_scrollBarSize == null) {
		_scrollBarSize = {};
		
		var outer = $('<div style="width: 100px; height: 100px; overflow: auto; position: absolute; left: -1000px; top: -1000px;"><div></div></div>').appendTo('body'),
			inner = outer.children().first();
		
		inner.css('height', '200px');
		_scrollBarSize.vert = outer.width() - inner.width();
		
		inner.css({ height: '100%', width: '200px' });
		var ih = inner.height(),
			oh = outer.height();
		
		_scrollBarSize.horiz = (ih == 0 || ih > oh) ? _scrollBarSize.vert : oh - ih;
		
		outer.remove();
	}
	
	return _scrollBarSize;
};

})(jQuery);