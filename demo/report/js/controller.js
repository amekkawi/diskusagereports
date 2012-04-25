/* 
 * Copyright (c) 2011 André Mekkawi <diskusage@andremekkawi.com>
 * Version: $Source Version$
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

var Controller;

;(function($){

Controller = function() {
	var self = this;
	
	// Add the built-in languages.
	this.addLanguage([ [ 'en-us', 'English (US)' ] ]);
	
	$('#Container').show();
	
	// Used to determine change in value.
	this._lastHash = null;
	this._lastSection = null;
	this._lastSectionOptions = null;
	this._lastTreeOptions = null;
	
	// jQuery references
	this._sections = $('#Sections');
	this._filesSection = $('#Section_Files');
	this._typesSection = $('#Section_Types');
	this._sizesSection = $('#Section_Sizes');
	this._modifiedSection = $('#Section_Modified');
	this._subdirsSection = $('#Section_SubDirs');
	this._top100Section = $('#Section_Top100');
	
	this.suffix = [ '.txt', '' ];
	
	this.gradient = 
		['007eff','0081f8','0084ef','0088e4','008dd8','0092cb','0097bd','009cae',
		 '00a29f','00a88f','00ae80','00b275','00b66a','00ba60','00be56','00c24c',
		 '00c642','00ca38','00cd2f','00d127','00d41f','00d717','00da10','03dd0a',
		 '07e005','0be200','15e600','22eb00','31ef00','42f300','55f600','69fa00',
		 '7dfd00','8bfe00','93ff00','9cff00','a5ff00','adff00','b5ff00','bdff00',
		 'c5ff00','cdff00','d4ff00','dbff00','e2ff00','e8ff00','eeff00','f3ff00',
		 'f8ff00','fcff00','ffff00','fffd00','fffa00','fff800','fff500','fff200',
		 'ffee00','ffea00','ffe600','ffe200','ffde00','ffd900','ffd400','ffcf00',
		 'ffca00','ffc500','ffc000','ffba00','ffb500','ffb000','ffaa00','ffa500',
		 'ffa000','ff9a00','ff9500','ff9000','ff8d00','ff8900','ff8600','ff8200',
		 'ff7f00','ff7b00','ff7700','ff7300','ff6f00','ff6b00','ff6700','ff6300',
		 'ff5f00','ff5a00','ff5600','ff5200','ff4e00','ff4900','ff4500','ff4100',
		 'ff3100','ff2200','ff1400','ff0900' ];
	
	// Set the report's name, which determines the path to find the report data.
	this.report = window.location.search.substring(1);
	
	// Set the maximum number of rows per page.
	this.pageMax = 100;
	
	// Cache the scroll bar width/height.
	$.scrollBarSize();
	
	// Adjust heights when the window is resized.
	var resizeTimeout, resizeCounter = 0, resizeFn = function(){
		resizeCounter = 0;
		self.resizeWindow();
	};
	$(window).resize(function(){
		if (resizeCounter >= 500) {
			resizeFn();
		}
		else {
			if (resizeTimeout) clearTimeout(resizeTimeout);
			resizeTimeout = setTimeout(resizeFn, resizeCounter += 150);
		}
	});
	
	$('.pager a[href], #Sections a[href]').live('click', function(ev) {
		self.setOptions(self._parseLocation($(this).attr('href')));
		ev.preventDefault();
	});
	
	// Execute the initializers for all the separate modules.
	for (var i in this.inits) this.inits[i].call(this);
	
	// Initial adjustments to height.
	this.resizeWindow();
	
	// Create the history helper.
	$.history = new History();
};

$.extend(Controller.prototype, {
	
	inits: [],
	
	_ajaxStage: null,
	_debugTimeoutFn: function(fn, timeout) { /*return setTimeout(fn, timeout);*/ fn(); return null; },
	_debugTimeout: 500,
	_preLoad: true,
	
	// Directory lookup.
	directories: null,
	
	// Default URL for the report data.
	reportsBaseURL: 'data/',
	
	options: null,
	defaultOptions: {
		hash: null,
		section: 'subdirs',
		totalsSortBy: 'byte',
		totalsSortRev: true,
		filesSortBy: 'size',
		filesSortRev: true,
		top100SortBy: 'size',
		top100SortRev: true,
		treeSortBy: 'label',
		treeSortRev: false,
		page: 1
	},
	
	load: function() {
		var self = this;
		
		// Mark that load() has been called.
		this._preLoad = false;
		
		// Make sure the default language is added.
		this.addLanguage(this.defaultLanguage);
		
		// Set the language.
		// Note: Also makes the header/footer visible.
		this.setLanguage(!this.language ? this.defaultLanguage : this.language, function(ret, message) {
			if (ret) {
				$('#Loading').html(self.translate('loading_settings'));
				
				self._debugTimeoutFn(function(){
					self._downloadSettings();
				}, self._debugTimeout);
			}
			else {
				$('#Loading').text('Language file could not be loaded: ' + message);
			}
		});
	},
	
	setLocation: function(location, completeFn) {
		// Parse the new location.
		this.options = $.extend({}, this.defaultOptions, this._parseLocation(location));
		
		// View the root hash if hash is not set.
		if (!this.options.hash) {
			this.options.hash = this.settings.root;
		}
		
		this.displayReport(completeFn);
	},
	
	setOptions: function(options, skipHistory) {
		$.extend(this.options, options);
		if (!skipHistory) {
			$.history.newItem(this._createLocation(this.options));
		}
		this.displayReport();
	},
	
	displayReport: function(completeFn) {
		var self = this;
		
		// If the hash changed, load the data for the new directory.
		if (this._lastHash != this.options.hash) {
			this._debugTimeoutFn(function(){
				self._downloadHash(completeFn);
			}, this._debugTimeout);
		}
		else {
			this._populateReport();
			if ($.isFunction(completeFn)) completeFn();
		}
	},
	
	_downloadHash: function(completeFn) {
		var self = this;
		
		if (this._xhr) this._xhr.abort();
		this._xhr = $.ajax({
			cache: false,
			url: this.reportsBaseURL + this.report + '/' + this.options.hash + this.settings.suffix,
			type: 'GET',
			dataType: 'json',
			error: function(xhr, status, ex) {
				// Clear the data since it was not loaded.
				self._data = null;
				
				switch (status) {
					case 'parsererror':
						$('#Error').html(self.translate('hash_parse_error'));
						break;
					case 'timeout':
						$('#Error').html(self.translate('hash_timeout_error'));
						break;
					case 'error':
						switch (xhr.status) {
							case 404:
								$('#Error').html(self.translate('hash_notfound_error'));
								break;
							case 401:
								$('#Error').html(self.translate('hash_username_error'));
								break;
							default:
								$('#Error').html(self.translate('hash_unknown_error', xhr.status));
						}
						break;
					default:
						$('#Error').html(self.translate('hash_unknown_error', status));
				}
				
				// Attempt to select the tree node.
				// This may be possible if the hash exists but its data could not be loaded.
				if (self.directories) {
					self._tree.tree('select', self.options.hash);
				}
			},
			success: function(data, status, xhr) {
				// Set the loaded data.
				self._data = data;
				
				if (self.directories) {
					// Determine the path to the selected directory.
					var hashPath = [];
					for (var i = 0; i < data.parents.length; i++) {
						hashPath.push(data.parents[i].hash);
					}
					hashPath.push(self.options.hash);
					
					// Load that path in the tree.
					self._tree.tree('select', hashPath);
				}
			},
			complete: function() {
				
				// Reset the page, unless this is the first load.
				if (self._lastHash != null) self.options.page = 1;
				
				self._lastHash = self.options.hash;
				
				// Reset the section so it is freshly loaded.
				self._lastSection = self._lastSectionOptions = null;
				
				$('#Error')[self._data ? 'hide' : 'show']();
				$('#RightColumn')[self._data ? 'show' : 'hide']();
				
				self._populateReport();
				
				// Correct the height of divs.
				self.resizeWindow();
				
				if ($.isFunction(completeFn)) completeFn();
			}
		});
	},
	
	_parseLocation: function(location) {
		// Make sure the location is an array.
		if ($.isString(location)) {
			if (location.indexOf('http://') == 0 && location.indexOf('#') >= 0) location = location.substring(location.indexOf('#'));
			if (location.indexOf('#') == 0) location = location.substring(1);
			location = location.parseQS();
		}
		
		// Reset options to defaults.
		var opts = {};
	
		// Validate and set options.
		if (location.h && location.h.match(/^[a-f0-9]{32}$/i)) {
			opts.hash = location.h.toLowerCase();
		}
		if (location.s && location.s.match(/^(subdirs|files|modified|types|sizes|top100)$/i)) {
			opts.section = location.s.toLowerCase();
		}
		if (location.tsb && location.tsb.match(/^(label|byte|num)$/)) {
			opts.totalsSortBy = location.tsb;
		}
		if (location.tsr && location.tsr.match(/^[01]$/)) {
			opts.totalsSortRev = location.tsr == '1';
		}
		if (location.fsb && location.fsb.match(/^(name|type|size|modified)$/)) {
			opts.filesSortBy = location.fsb;
		}
		if (location.fsr && location.fsr.match(/^[01]$/)) {
			opts.filesSortRev = location.fsr == '1';
		}
		if (location.bsb && location.bsb.match(/^(name|type|size|modified|path)$/)) {
			opts.top100SortBy = location.bsb;
		}
		if (location.bsr && location.bsr.match(/^[01]$/)) {
			opts.top100SortRev = location.bsr == '1';
		}
		if (location.dsb && location.dsb.match(/^(name|byte|num)$/)) {
			opts.treeSortBy = location.dsb;
		}
		if (location.dsr && location.dsr.match(/^[01]$/)) {
			opts.treeSortRev = location.dsr == '1';
		}
		if (location.p && location.p.match(/^[0-9]+$/)) {
			opts.page = parseInt(location.p);
		}
		
		return opts;
	},
	
	_createLocation: function(options, type) {
		var opts = $.extend({}, this.options, options),
			hash = section = totalsSort = filesSort = top100Sort = treeSort = page = true;
		
		switch (type) {
			case 'path':
			case 'contents':
			case 'top100':
				page = false;
			case 'pager':
				treeSort = false;
				break;
		}
		
		return ((hash ? '&h=' + escape(opts.hash) : '')
			+ (section ? '&s=' + escape(opts.section) : '')
			+ (totalsSort ? '&tsb=' + escape(opts.totalsSortBy) : '')
			+ (totalsSort ? '&tsr=' + escape(opts.totalsSortRev ? '1' : '0') : '')
			+ (filesSort ? '&fsb=' + escape(opts.filesSortBy) : '')
			+ (filesSort ? '&fsr=' + escape(opts.filesSortRev ? '1' : '0') : '')
			+ (top100Sort ? '&bsb=' + escape(opts.top100SortBy) : '')
			+ (top100Sort ? '&bsr=' + escape(opts.top100SortRev ? '1' : '0') : '')
			+ (treeSort ? '&dsb=' + escape(opts.treeSortBy) : '')
			+ (treeSort ? '&dsr=' + escape(opts.treeSortRev ? '1' : '0') : '')
			+ (page ? '&p=' + escape(opts.page) : '')).substring(1);
	},
	
	resizeWindow: function() {
		var footerHeight = $('#Footer').outerHeight();
		
		// Set the correct height for the container.
		var containerHeightDiff = $('#Container').outerHeight(true) - $('#Container').height();
		$('#Container').height($('body').height() - containerHeightDiff - $('#Container').offset().top - footerHeight - 5);
		
		$('#ErrorsDialog, #LanguageDialog').dialog('resize');
			
		if ($('#LeftColumn').is(':visible')) {
			var leftColumnBorderHeightDiff = $('#LeftColumnBorder').outerHeight(true) - $('#LeftColumnBorder').height();
			$('#LeftColumnBorder').height($('#LeftColumn').height() - leftColumnBorderHeightDiff);
			
			var leftColumnScrollerHeightDiff = $('#LeftColumnScroller').outerHeight(true) - $('#LeftColumnScroller').height();
			$('#LeftColumnScroller').height($('#LeftColumnBorder').height() - $('#LeftColumnScroller').position().top - leftColumnScrollerHeightDiff);
			
			$('#LeftColumn').width(Math.max(100, Math.min($('#Columns').width() - 200, $('#LeftColumn').width())));
		}
		
		if ($('#Report').is(':visible')) {
			var reportHeightDiff = $('#Report').outerHeight(true) - $('#Report').height();
			$('#Report').height($('#RightColumn').height() - reportHeightDiff - $('#Report').position().top);
		}
	}
	
});

})(jQuery);