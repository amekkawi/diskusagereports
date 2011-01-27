;(function($) {

$.widget("ui.tree", {
	options: {
		expandRoot: true,
		data: null,
		root: null
	},
	
	_create: function() {
		var self = this, $elem = $(this.element).disableTextSelection().addClass(this.widgetBaseClass);
		
		if (this.options.data == null || this.options.root == null) {
			throw "data and root must be specified in the options for ui.tree.";
		}
		
		this._data = this.options.data;
		
		if ($.isUndefined(this._data[this.options.root])) {
			throw "root was not found in the provided.";
		}
		
		$elem.click(function(eo) {
			var id, li, target = $(eo.target);
			
			if (target.hasClass(self.widgetBaseClass + '-icon') || target.hasClass(self.widgetBaseClass + '-expander')) {
				li = target.closest('li');
				id = li.attr('id');
			}
			
			if (li && $.isString(id) && id.indexOf(self.widgetBaseClass + '_') == 0) {
				var hash = id.substring((self.widgetBaseClass + '_').length);
				
				if (target.hasClass(self.widgetBaseClass + '-expander')) {
					if (li.hasClass(self.widgetBaseClass + '-open')) {
						li.removeClass(self.widgetBaseClass + '-open');
						$('> ul', li).hide();
					}
					else if ($('> ul', li).size() != 0) {
						li.addClass(self.widgetBaseClass + '-open');
						$('> ul', li).show();
					}
					else {
						li.addClass(self.widgetBaseClass + '-open');
						li.append(self._createUL(hash));
					}
				}
				else {
					self._trigger('selection', {}, [ hash ]);
				}
			}
		});
		
		$elem.append('<ul>' + this._createLI(this.options.root, this._data[this.options.root].name, this.widgetBaseClass + '-root ' + this.widgetBaseClass + '-open') + '</ul>');
		
		$('#' + this.widgetBaseClass + '_' + this.options.root).append(this._createUL(this.options.root));
	},
	
	_createUL: function(hash) {
		var html = '<ul>';
		var subdirs = this._data[hash].subdirs;
		for (var i = 0; i < subdirs.length; i++) {
			html += this._createLI(subdirs[i].hash, subdirs[i].name, this._data[subdirs[i].hash].subdirs.length > 0 ? '' : this.widgetBaseClass + '-nosubdirs');
		}
		
		html += '</ul>';
		
		return html;
	},
	
	_createLI: function(hash, name, classes) {
		return '<li '+ ($.isString(classes) ? ' class="' + classes + '" ' : '') +' id="' + this.widgetBaseClass + '_' + hash.htmlencode() + '"><div class="' + this.widgetBaseClass + '-expander"><div class="' + this.widgetBaseClass + '-icon">' + name.htmlencode() + '</div></div></li>';
	}
});

})(jQuery);