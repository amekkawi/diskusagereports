var Controller;

;(function($){

Controller = function() {
	var self = this;
	
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
	
	this.gradient = [ '007eff','0081f8','0084ef','0088e4','008dd8','0092cb','0097bd','009cae','00a29f','00a88f','00ae80','00b275','00b66a','00ba60','00be56','00c24c','00c642','00ca38','00cd2f','00d127','00d41f','00d717','00da10','03dd0a','07e005','0be200','15e600','22eb00','31ef00','42f300','55f600','69fa00','7dfd00','8bfe00','93ff00','9cff00','a5ff00','adff00','b5ff00','bdff00','c5ff00','cdff00','d4ff00','dbff00','e2ff00','e8ff00','eeff00','f3ff00','f8ff00','fcff00','ffff00','fffd00','fffa00','fff800','fff500','fff200','ffee00','ffea00','ffe600','ffe200','ffde00','ffd900','ffd400','ffcf00','ffca00','ffc500','ffc000','ffba00','ffb500','ffb000','ffaa00','ffa500','ffa000','ff9a00','ff9500','ff9000','ff8d00','ff8900','ff8600','ff8200','ff7f00','ff7b00','ff7700','ff7300','ff6f00','ff6b00','ff6700','ff6300','ff5f00','ff5a00','ff5600','ff5200','ff4e00','ff4900','ff4500','ff4100','ff3100','ff2200','ff1400','ff0900' ];
	
	// Set the report's name, which determines the path to find the report data.
	this.report = window.location.search.substring(1);
	
	// Calculate the width (which caches it).
	$.scrollbarWidth();
	
	// Adjust heights when the window is resized.
	$(window)
		.resize(function(){
			if (document.timeout_resize) clearTimeout(document.timeout_resize);
			document.timeout_resize = setTimeout(function() {
				self.resizeWindow();
			}, 100);
		});
	
	// Initial adjustments to height.
	this.resizeWindow();
};

$.extend(Controller.prototype, {
	
	_ajaxStage: null,
	_debugTimeout: 250,
	
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
		treeSortRev: false
	},
	
	load: function() {
		var self = this;
		$('#Loading').text('Loading Report Settings...');
		
		setTimeout(function(){
			self._downloadSettings();
		}, this._debugTimeout);
	},
	
	setLocation: function(location, completeFn) {
		// Parse the new location.
		this.options = this._parseLocation(location);
		
		// View the root hash if hash is not set.
		if (!this.options.hash) {
			this.options.hash = this.settings.root;
		}
		
		this.displayReport(completeFn);
	},
	
	setOptions: function(options, skipHistory) {
		$.extend(this.options, options);
		if (!skipHistory) {
			dhtmlHistory.add(this._createLocation(this.options), null);
		}
		this.displayReport();
	},
	
	displayReport: function(completeFn) {
		// If the hash changed, load the data for the new directory.
		if (this._lastHash != this.options.hash) {
			this._downloadHash(completeFn);
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
			url: this.reportsBaseURL + this.report + '/' + this.options.hash,
			type: 'GET',
			dataType: 'json',
			error: function(xhr, status, ex) {
				// Clear the data since it was not loaded.
				self._data = null;
				
				switch (status) {
					case 'parsererror':
						$('#Error').text('Error: Data for this directory is invalid or could not be parsed.');
						break;
					case 'timeout':
						$('#Error').text('Error: Download took to long and timed out. Reload to try again.');
						break;
					case 'error':
						switch (xhr.status) {
							case 404:
								$('#Error').text('Error: Not found. The data for this directory may be missing.');
								break;
							case 401:
								$('#Error').text('Error: A username and password is required. Reload to try again.');
								break;
							default:
								$('#Error').text('Error: An unknown error occurred (HTTP Status ' + xhr.status + '). Reload to try again.');
						}
						break;
					default:
						$('#Error').text('Error: An unknown error occurred. Reload to try again.');
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
			location = location.parseQS();
		}
		
		// Reset options to defaults.
		var opts = $.extend({}, this.defaultOptions);
	
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
		
		return opts;
	},
	
	_createLocation: function(options) {
		var opts = $.extend({}, this.options, options);
		return 'h=' + escape(opts.hash)
			+ '&s=' + escape(opts.section)
			+ '&tsb=' + escape(opts.totalsSortBy)
			+ '&tsr=' + escape(opts.totalsSortRev ? '1' : '0')
			+ '&fsb=' + escape(opts.filesSortBy)
			+ '&fsr=' + escape(opts.filesSortRev ? '1' : '0')
			+ '&bsb=' + escape(opts.top100SortBy)
			+ '&bsr=' + escape(opts.top100SortRev ? '1' : '0')
			+ '&dsb=' + escape(opts.treeSortBy)
			+ '&dsr=' + escape(opts.treeSortRev ? '1' : '0');
	},
	
	resizeWindow: function() {
		var footerHeight = $('#Footer').outerHeight();
		
		// Set the correct height for the container.
		var containerHeightDiff = $('#Container').outerHeight(true) - $('#Container').height();
		$('#Container').height($('body').height() - containerHeightDiff - $('#Container').offset().top - footerHeight - 5);
		
		if ($('#Errors').is(':visible')) {
			var errorsHeightDiff = $('#ErrorsInner').outerHeight(true) - $('#ErrorsInner').height();
			$('#ErrorsInner').height($('#Errors').height() - errorsHeightDiff);
			
			var errorsScrollerHeightDiff = $('#ErrorsScroller').outerHeight(true) - $('#ErrorsScroller').height();
			$('#ErrorsScroller').height($('#ErrorsInner').height() - errorsScrollerHeightDiff - $('#ErrorsScroller').position().top);
		}
			
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