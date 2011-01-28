;(function($) {

$.widget("ui.tree", {
	options: {
		expandRoot: true,
		data: null,
		root: null,
		expandOnSelect: true,
		closeOthersOnSelect: true
	},
	
	select: function(hash, li) {
		var self = this,
			parents = [];
		
		if ($.isArray(hash)) {
			parents = hash;
			hash = parents.pop();
		}
		
		if (hash != this._lastHash) {
			this._lastHash = hash;
			
			for (var i = 0; i < parents.length; i++) {
				this.open(parents[i]);
			}
			
			if (!li) li = $('#' + this.widgetBaseClass + '_' + hash);
			
			$('li.selected', this.element).removeClass('selected');
			li.addClass('selected');
			
			if (this.options.closeOthersOnSelect) {
				$('li.' + this.widgetBaseClass + '-open').each(function(){
					if (!$(this).equals(li) && !li.isParentOf($(this)) && !li.isChildOf($(this))) {
						self.close(null, $(this));
					}
				});
			}
			
			if (this.options.expandOnSelect) this.open(hash, li);
		}
	},
	
	open: function(hash, li) {
		if (!li) li = $('#' + this.widgetBaseClass + '_' + hash);
		
		if ($('> ul', li).size() != 0) {
			li.addClass(this.widgetBaseClass + '-open');
			$('> ul', li).show();
		}
		else {
			li.addClass(this.widgetBaseClass + '-open');
			li.append(this._createUL(hash));
		}
	},
	
	close: function(hash, li) {
		if (!li) li = $('#' + this.widgetBaseClass + '_' + hash);
		
		if (!li.hasClass(this.widgetBaseClass + '-root')) {
			li.removeClass(this.widgetBaseClass + '-open');
			$('> ul', li).hide();
		}
	},
	
	toggle: function(hash, li) {
		if (!li) li = $('#' + this.widgetBaseClass + '_' + hash);
		
		if (li.hasClass(this.widgetBaseClass + '-open')) {
			this.close(hash, li);
		}
		else {
			this.open(hash, li);
		}
	},
	
	_create: function() {
		var self = this,
			$elem = $(this.element).disableTextSelection().addClass(this.widgetBaseClass);
		
		if (this.options.data == null || this.options.root == null) {
			throw "data and root must be specified in the options for ui.tree.";
		}
		
		this._lastHash = null;
		this._data = this.options.data;
		
		if ($.isUndefined(this._data[this.options.root])) {
			throw "root was not found in the provided.";
		}
		
		$elem.click(function(eo) {
			var id, li, target = $(eo.target);
			
			if (target.closest('div.' + self.widgetBaseClass + '-expander').size() > 0) {
				li = target.closest('li');
				id = li.attr('id');
			}
			
			if (li && $.isString(id) && id.indexOf(self.widgetBaseClass + '_') == 0) {
				var hash = id.substring((self.widgetBaseClass + '_').length);
				
				if (target.hasClass(self.widgetBaseClass + '-expander')) {
					self.toggle(hash, li);
				}
				else {
					self.select(hash, li);
					self._trigger('selection', {}, [ hash ]);
				}
			}
		});
		
		$elem.append('<ul>' + this._createLI(this.options.root, this._data[this.options.root].name, this.widgetBaseClass + '-root ' + this.widgetBaseClass + '-open') + '</ul>');
		
		$('#' + this.widgetBaseClass + '_' + this.options.root).append(this._createUL(this.options.root));
	},
	
	_createUL: function(hash) {
		var li = [],
			subdirs = this._data[hash].subdirs;
		
		for (var i = 0; i < subdirs.length; i++) {
			
			var html = this._createLI(subdirs[i].hash, subdirs[i].name, this._data[subdirs[i].hash].subdirs.length > 0 ? '' : this.widgetBaseClass + '-nosubdirs');
			
			var index = BinarySearch(li, subdirs[i].name.toLowerCase(), function(needle, item, index) {
				if (needle < item[0]) return -1;
				if (needle > item[0]) return 1;
				return 0;
			});
			
			if (index < 0) {
				li.splice(Math.abs(index)-1, 0, [subdirs[i].name.toLowerCase(), html]);
			}
			else {
				li.splice(index, 0, [subdirs[i].name.toLowerCase(), html]);
			}
		}
		
		var finalHTML = '';
		for (var i = 0; i < li.length; i++) {
			finalHTML += li[i][1];
		}
		
		return '<ul>' + finalHTML + '</ul>';
	},
	
	_createLI: function(hash, name, classes) {
		return '<li '+ ($.isString(classes) ? ' class="' + classes + '" ' : '') +' id="' + this.widgetBaseClass + '_' + hash.htmlencode() + '"><div class="' + this.widgetBaseClass + '-expander"><div class="' + this.widgetBaseClass + '-icon"><span>' + name.htmlencode() + '</span></div></div></li>';
	}
});

})(jQuery);