/* 
 * Copyright (c) 2011 André Mekkawi <diskusage@andremekkawi.com>
 * Version: $Source Version$
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

;(function($) {

$.widget("ui.scrollerResize", {
	
	options: {
		contents: '>*:first',
		scroller: null
	},
	
	_create: function() {
		var self = this,
			$elem = $(this.element).addClass(this.widgetBaseClass);
		
		this._scroller = this.options.scroller ? $(this.options.scroller) : $elem;
		this._contents = this._scroller.find(this.options.contents);
		this._parent = null;
	},
	
	resize: function(center) {
		var self = this,
			$elem = $(this.element),
			scrollBar = $.scrollBarSize();
		
		if (!this._parent) {
			this._parent = $elem.offsetParent();
			
			// Use window instead of 'body' since we are likely
			// trying to adjust the scroller to the visible area,
			// and measuring the height of 'body' is buggy.
			if (this._parent.is('body')) {
				this._parent = $(window);
				this._parentFn = { width: 'width', height: 'height' };
			}
			else {
				this._parentFn = { width: 'outerWidth', height: 'outerHeight' };
			}
		}
		
		this._scroller.css('white-space', 'nowrap');
		
		var diff = { width: $elem.outerWidth(true) - this._scroller.width(), height: $elem.outerHeight(true) - this._scroller.height() },
			max = { width: this._parent[this._parentFn.width]() - diff.width, height: this._parent[this._parentFn.height]() - diff.height },
			contents = { width: this._contents.outerWidth(true) },
			size = { width: Math.min(max.width, contents.width) };
		
		this._scroller.width(size.width);
		
		// Determine the new width/height now that the width has been set.
		contents = { width: this._contents.outerWidth(true), height: this._contents.outerHeight(true) },
		size.height = Math.min(max.height, contents.height);
		
		if (size.height < contents.height) {
			size.width = Math.min(max.width, size.width + scrollBar.vert);
		}
		if (size.width < contents.width) {
			size.height = Math.min(max.height, size.height + scrollBar.horiz);
		}
		
		if (center) {
			$elem.css({ left: (max.width - size.width) / 2 , top: (max.height - size.height) / 2 });
		}
		
		this._scroller.css(size);
	}
});

})(jQuery);