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

$.widget("ui.tree", {
	options: {
		expandRoot: true,
		data: null,
		root: null,
		expandOnSelect: true,
		closeOthersOnSelect: true,
		comparator: function(a, b) {
			if (a.name.toLowerCase() < b.name.toLowerCase()) return -1;
			if (a.name.toLowerCase() > b.name.toLowerCase()) return 1;
			return 0;
		},
		getPrefix: null
	},
	
	select: function(hash, li) {
		var self = this,
			parents = [];
		
		if ($.isArray(hash)) {
			parents = hash;
			hash = parents.pop();
		}
		
		if (!this._data[hash]) {
			this.deselect();
			return;
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
			
			if (li.hasClass('ui-tree-root')) {
				$('#LeftColumnScroller').scrollTop(0).scrollLeft(0);
			}
			else {
				$('#LeftColumnScroller').scrollIntoView(li);
			}
		}
	},
	
	deselect: function() {
		$('li.selected', this.element).removeClass('selected');
		this._lastHash = null;
	},
	
	open: function(hash, li) {
		if (!li) li = $('#' + this.widgetBaseClass + '_' + hash);
		else li = $(li);
		
		if (!li.hasClass(this.widgetBaseClass + '-open') && !li.hasClass(this.widgetBaseClass + '-nosubdirs')) {
			if ($('> ul', li).size() != 0) {
				li.addClass(this.widgetBaseClass + '-open');
				this.resort($('> ul', li));
				$('> ul', li).show();
			}
			else {
				li.addClass(this.widgetBaseClass + '-open');
				li.append(this._createUL(hash));
			}
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
		this._filesData = [];
		
		if ($.isUndefined(this._data[this.options.root])) {
			throw "root was not found in the provided directory data.";
		}
		
		$elem.click(function(eo) {
			var id, li, target = $(eo.target), files = false;
			
			if (target.closest('div.' + self.widgetBaseClass + '-expander', self.element).size() > 0) {
				li = target.closest('li', self.element);
				id = li.attr('id');
			}
			
			if (li && $.isString(id) && id.indexOf(self.widgetBaseClass + '_') == 0) {
				var hash = id.substring((self.widgetBaseClass + '_').length);
				
				if (hash.indexOf('files_') == 0) {
					hash = hash.substring('files_'.length);
					files = true;
					li = null;
				}
				
				if (target.hasClass(self.widgetBaseClass + '-expander')) {
					self.toggle(hash, li);
				}
				else {
					self.select(hash, li);
					self._trigger('selection', {}, [ hash, files ]);
				}
			}
		});
		
		$elem.append('<ul class="' + this.widgetBaseClass + '-rootul">' + this._createLI(this.options.root, this._data[this.options.root].name, this.widgetBaseClass + '-root ' + this.widgetBaseClass + '-open') + '</ul>');
		
		$('#' + this.widgetBaseClass + '_' + this.options.root).append(this._createUL(this.options.root));
	},
	
	_createUL: function(parenthash) {
		if (!this._data[parenthash]) return '';
		
		var sorted = [],
			self = this,
			parentdata = this._data[parenthash],
			subdirs = parentdata.subdirs,
			subbytes = 0,
			subnum = 0;
		
		for (var i = 0; i < subdirs.length; i++) {
			var hash = $.isString(subdirs[i]) ? subdirs[i] : subdirs[i].hash;
			var data = this._data[hash];
			var html = this._createLI(hash, data.name, data.subdirs.length > 0 ? '' : this.widgetBaseClass + '-nosubdirs', this.options.getPrefix ? this.options.getPrefix(data, parentdata) : '');
			
			subbytes += parseInt(data.totalbytes);
			subnum += parseInt(data.totalnum);
			
			var index = BinarySearch(sorted, data, function(needle, item, index) {
				return self.options.comparator(needle, item[0]);
			});
			
			if (index < 0) {
				sorted.splice(Math.abs(index)-1, 0, [data, html]);
			}
			else {
				sorted.splice(index, 0, [data, html]);
			}
		}
		
		this._filesData[parenthash] = {
			files: true,
			name: '',
			totalbytes: parseInt(parentdata.totalbytes) - subbytes,
			totalnum: parseInt(parentdata.totalnum) - subnum
		};
		
		// Create the "Files in this directory" node.
		var html = this._createLI(parenthash, 'Files in this directory', this.widgetBaseClass + '-files ' + this.widgetBaseClass + '-nosubdirs', this.options.getPrefix ? this.options.getPrefix(this._filesData[parenthash], parentdata) : '', true);
		
		// Determine where the "Files in this directory" node should go.
		var index = BinarySearch(sorted, this._filesData[parenthash], function(needle, item, index) {
			return self.options.comparator(needle, item[0]);
		});
		
		// Insert the "Files in this directory" node.
		if (index < 0) {
			sorted.splice(Math.abs(index)-1, 0, [null, html]);
		}
		else {
			sorted.splice(index, 0, [null, html]);
		}
		
		// Mark the 'last' and 'lastdir' nodes.
		if (sorted.length) {
			var classIndex = sorted[sorted.length-1][1].indexOf(' class="');
			sorted[sorted.length-1][1] = sorted[sorted.length-1][1].substring(0, classIndex + ' class="'.length) + this.widgetBaseClass + '-last ' + sorted[sorted.length-1][1].substring(classIndex + ' class="'.length);
			
			if (sorted[sorted.length-1][0] == null && sorted.length > 1) {
				classIndex = sorted[sorted.length-1][1].indexOf(' class="');
				sorted[sorted.length-2][1] = sorted[sorted.length-2][1].substring(0, classIndex + ' class="'.length) + this.widgetBaseClass + '-lastdir ' + sorted[sorted.length-2][1].substring(classIndex + ' class="'.length);
			}
		}
		
		var finalHTML = '';
		for (var i = 0; i < sorted.length; i++) {
			finalHTML += sorted[i][1];
		}
		
		return '<ul>' + finalHTML + '</ul>';
	},
	
	_createLI: function(hash, name, classes, prefix, isfiles) {
		return '<li '+ ($.isString(classes) ? ' class="' + classes + '" ' : '') +' id="' + this.widgetBaseClass + '_' + (isfiles ? 'files_' : '') + hash.htmlencode() + '"><div class="' + this.widgetBaseClass + '-linecover"><div class="' + this.widgetBaseClass + '-expander"><div class="' + this.widgetBaseClass + '-icon"><span class="' + this.widgetBaseClass + '-prefix">'+ ($.isString(prefix) && prefix != '' ? prefix.htmlencode() + ' ' : '') +'</span><span class="' + this.widgetBaseClass + '-label">' + name.htmlencode() + '</span></div></div></div></li>';
	},
	
	resort: function(startUL) {
		var self = this,
			ul,
			subUL,
			stack = [ startUL ? startUL : $('>ul>li>ul', this.element).get(0) ];
		
		while ($.isDefined(ul = stack.pop())) {
			var parentLi = $(ul).closest('li[id]', this.element),
				parenthash = parentLi.size() ? parentLi.attr('id').substr((this.widgetBaseClass + '_').length) : '',
				sorted = [],
				li = $('>li', ul);
			
			li.removeClass(this.widgetBaseClass + '-last ' + this.widgetBaseClass + '-lastdir');
			
			li.each(function(){
				var $this = $(this),
					hash = $this.attr('id').substr((self.widgetBaseClass + '_').length),
					data = self._data[hash];
				
				if ($this.hasClass(self.widgetBaseClass + '-files')) {
					hash = hash.substr('files_'.length);
					data = self._filesData[hash];
				}
				
				if (self.options.getPrefix && parentLi.size()) {
					var prefix = self.options.getPrefix(data, self._data[parenthash]);
					$('span.' + self.widgetBaseClass + '-prefix', $this).text($.isString(prefix) ? prefix + ' ' : '');
				}
				
				if ($this.hasClass(self.widgetBaseClass + '-open') && (subUL = $('>ul', $this))) {
					stack.push(subUL);
				}
				
				var index = BinarySearch(sorted, data, function(needle, item, index) {
					return self.options.comparator(needle, item[0]);
				});
				
				if (index < 0) {
					sorted.splice(Math.abs(index)-1, 0, [data, this]);
				}
				else {
					sorted.splice(index, 0, [data, this]);
				}
			});
			
			if (sorted.length) {
				var last = $(sorted[sorted.length-1][1]).addClass(this.widgetBaseClass + '-last');
				if (sorted.length > 1 && last.hasClass(this.widgetBaseClass + '-files')) {
					$(sorted[sorted.length-2][1]).addClass(this.widgetBaseClass + '-lastdir');
				}
			}
			
			$(ul).append($($.map(sorted, function(item){ return item[1]; })));
		}
	}
});

})(jQuery);