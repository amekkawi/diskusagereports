/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereport.sf.net/license.html
 */

;(function($) {

$.widget("ui.dialog", {
	
	options: {
		opening: null,
		opened: null,
		closed: null,
		title: null,
		roundedBorders: true,
		shadow: true,
		closeButton: true,
		dimmer: true,
		borderColor: null,
		backgroundColor: null,
		titleColor: null,
		textColor: null,
		borderSize: 10
	},
	
	_create: function() {
		var self = this,
			$elem = $(this.element).addClass(this.widgetBaseClass).hide();
		
		this._parent = $elem.parent();
		
		this._contents = $elem
			.wrapInner('<div class="' + this.widgetBaseClass + '-contents">')
			.children().first();
		
		this._scroller = this._contents.wrap('<div class="' + this.widgetBaseClass + '-scroller">').parent();
		
		$('<div style="clear: both;">').appendTo(this._scroller);
		
		this._title = $('<div class="' + this.widgetBaseClass + '-title' + '">').insertBefore(this._scroller);
		
		this._close = $('<div class="' + this.widgetBaseClass + '-close' + '">')
			.click(function(){ self.close(); })
			.appendTo($elem);
		
		this._dimmer = $('#' + this.widgetBaseClass + '-dimmer');
		
		if (this._dimmer.size() == 0)
			this._dimmer = $('<div>').attr('id', this.widgetBaseClass + '-dimmer').appendTo('body');
		
		$('#' + this.widgetBaseClass + '-dimmer').live('click', function() { if (self.options.dimmer) self.close(); });
		
		for (var key in this.options) {
			this._processOption(key, this.options[key]);
		}
	},
	
	option: function(key, value) {
		var $elem = $(this.element);
		
		if (key == "parent") value = $(value);
		
		if (this._processOption(key, value))
			$.Widget.prototype.option.call(this, key, value);
	},
	
	_processOption: function(key, value) {
		var $elem = $(this.element);
		
		switch (key) {
			case 'parent':
				if (!value.isParentOf($elem)) return false;
				break;
				
			case 'title':
				if ($.isString(value) && value.length > 0)
					this._title.text(value).show();
				else
					this._title.text('').hide();
				
				this.resize();
				break;
				
			case 'roundedBorders':
				$elem[value ? 'addClass' : 'removeClass'](this.widgetBaseClass + '-has-rounded-borders');
				break;
				
			case 'shadow':
				$elem[value ? 'addClass' : 'removeClass'](this.widgetBaseClass + '-has-shadow');
				break;
				
			case 'closeButton':
				this._close[value ? 'show' : 'hide']();
				break;
				
			case 'borderSize':
				if (!$.isNumber(value)) return false;
				$elem.css('border-width', value);
				this._close.css({ top: (-10 - value) + 'px', right: (-10 - value) + 'px' });
				this.resize();
				break;
				
			case 'borderColor':
				if (!$.isString(value)) return false;
				$elem.css('border-color', value);
				this._title.css('background-color', value);
				break;
				
			case 'backgroundColor':
				if (!$.isString(value)) return false;
				$elem.css('background-color', value);
				break;
				
			case 'titleColor':
				if (!$.isString(value)) return false;
				this._title.css('color', value);
				break;
				
			case 'textColor':
				if (!$.isString(value)) return false;
				this._contents.css('color', value);
				break;
		}
		
		return true;
	},
	
	open: function() {
		var self = this, $elem = $(this.element);
		
		this._trigger('opening');
		
		$elem.css({ left: '-100000px', top:  '-100000px' }).show();
		this.resize();
		
		if (this.options.dimmer)
			this._dimmer.show();
		
		this._trigger('opened');
	},
	
	close: function() {
		$(this.element).hide();
		if (this.options.dimmer) this._dimmer.hide();
		this._trigger('closed');
	},
	
	resize: function() {
		var self = this, $elem = $(this.element);
		if (!$elem.is(':visible')) return;
		
		this._scroller.css({  width: '', height: '' });
		this._contents.css({ 'white-space': 'nowrap' });
		
		$.each([ "Width", "Height" ], function( i, dimension ) {
			var parent = Math.round(self._parent['outer' + dimension]()),
				dialog = Math.round($elem['outer' + dimension](true)),
				contents = Math.round(self._contents['outer' + dimension](true)),
				diff = dialog - contents,
				size = Math.min(parent - diff, contents + 35);
			
			self._scroller[dimension.toLowerCase()](size);
			$elem.css(dimension == 'Width' ? 'left' : 'top', Math.round((parent - diff - size) / 2) + 'px');
		});
		
		this._contents.css('white-space', '');
	},
	
	contents: function(contents) {
		if (contents) {
			this._contents.html(contents);
			this.resize();
		}
		else {
			return this._contents;
		}
	}
});

})(jQuery);