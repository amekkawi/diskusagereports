/**
 * @author amekkawi
 */

var Viewer = function(opts) {
	var self = this;
	
	var colors = [ '007eff','0081f8','0084ef','0088e4','008dd8','0092cb','0097bd','009cae','00a29f','00a88f','00ae80','00b275','00b66a','00ba60','00be56','00c24c','00c642','00ca38','00cd2f','00d127','00d41f','00d717','00da10','03dd0a','07e005','0be200','15e600','22eb00','31ef00','42f300','55f600','69fa00','7dfd00','8bfe00','93ff00','9cff00','a5ff00','adff00','b5ff00','bdff00','c5ff00','cdff00','d4ff00','dbff00','e2ff00','e8ff00','eeff00','f3ff00','f8ff00','fcff00','ffff00','fffd00','fffa00','fff800','fff500','fff200','ffee00','ffea00','ffe600','ffe200','ffde00','ffd900','ffd400','ffcf00','ffca00','ffc500','ffc000','ffba00','ffb500','ffb000','ffaa00','ffa500','ffa000','ff9a00','ff9500','ff9000','ff8d00','ff8900','ff8600','ff8200','ff7f00','ff7b00','ff7700','ff7300','ff6f00','ff6b00','ff6700','ff6300','ff5f00','ff5a00','ff5600','ff5200','ff4e00','ff4900','ff4500','ff4100','ff3100','ff2200','ff1400','ff0900' ];
	
	// Set the inital options.
	this._options = $.extend({ gradient: colors }, this.defaults, opts);
	
	/*if ($.isUndefined(this._options.directories)) {
		throw "You must pass a directory lookup array to Viewer.";
	}*/
	if ($.isUndefined(this._options.settings)) {
		throw "You must pass the report settings to Viewer.";
	}
	if ($.isUndefined(this._options.report)) {
		throw "You must pass the report hash to Viewer.";
	}
	
	$('#Tabs li').disableTextSelection();
	
	$('#Tab_SubDirs').click(function(){
		self.changeOptions({ 'section': 'subdirs' });
	});
	$('#Tab_Modified').click(function(){
		self.changeOptions({ 'section': 'modified' });
	});
	$('#Tab_Types').click(function(){
		self.changeOptions({ 'section': 'types' });
	});
	$('#Tab_Sizes').click(function(){
		self.changeOptions({ 'section': 'sizes' });
	});
	$('#Tab_Files').click(function(){
		self.changeOptions({ 'section': 'files' });
	});
	$('#Tab_Top100').click(function(){
		self.changeOptions({ 'section': 'top100' });
	});
	
	$('#Sections .totals-sortby-label').disableTextSelection().click(function() {
		self.changeOptions({ totalsSortBy: 'label', totalsSortRev: self._options.totalsSortBy == 'label' ? !self._options.totalsSortRev : false });
	});
	$('#Sections .totals-sortby-byte').disableTextSelection().click(function() {
		self.changeOptions({ totalsSortBy: 'byte', totalsSortRev: self._options.totalsSortBy == 'byte' ? !self._options.totalsSortRev : true });
	});
	$('#Sections .totals-sortby-num').disableTextSelection().click(function() {
		self.changeOptions({ totalsSortBy: 'num', totalsSortRev: self._options.totalsSortBy == 'num' ? !self._options.totalsSortRev : true });
	});

	$('#Files-SortBy-name').disableTextSelection().click(function() {
		self.changeOptions({ filesSortBy: 'name', filesSortRev: self._options.filesSortBy == 'name' ? !self._options.filesSortRev : false });
	});
	$('#Files-SortBy-type').disableTextSelection().click(function() {
		self.changeOptions({ filesSortBy: 'type', filesSortRev: self._options.filesSortBy == 'type' ? !self._options.filesSortRev : false });
	});
	$('#Files-SortBy-size').disableTextSelection().click(function() {
		self.changeOptions({ filesSortBy: 'size', filesSortRev: self._options.filesSortBy == 'size' ? !self._options.filesSortRev : true });
	});
	$('#Files-SortBy-modified').disableTextSelection().click(function() {
		self.changeOptions({ filesSortBy: 'modified', filesSortRev: self._options.filesSortBy == 'modified' ? !self._options.filesSortRev : false });
	});

	$('#Top100-SortBy-name').disableTextSelection().click(function() {
		self.changeOptions({ top100SortBy: 'name', top100SortRev: self._options.top100SortBy == 'name' ? !self._options.top100SortRev : false });
	});
	$('#Top100-SortBy-type').disableTextSelection().click(function() {
		self.changeOptions({ top100SortBy: 'type', top100SortRev: self._options.top100SortBy == 'type' ? !self._options.top100SortRev : false });
	});
	$('#Top100-SortBy-size').disableTextSelection().click(function() {
		self.changeOptions({ top100SortBy: 'size', top100SortRev: self._options.top100SortBy == 'size' ? !self._options.top100SortRev : true });
	});
	$('#Top100-SortBy-modified').disableTextSelection().click(function() {
		self.changeOptions({ top100SortBy: 'modified', top100SortRev: self._options.top100SortBy == 'modified' ? !self._options.top100SortRev : false });
	});
	$('#Top100-SortBy-path').disableTextSelection().click(function() {
		self.changeOptions({ top100SortBy: 'path', top100SortRev: self._options.top100SortBy == 'path' ? !self._options.top100SortRev : false });
	});
	
	$('#LeftColumn .tree-sortby-label').disableTextSelection().click(function() {
		self.changeOptions({ treeSortBy: 'label', treeSortRev: self._options.treeSortBy == 'label' ? !self._options.treeSortRev : false });
	});
	$('#LeftColumn .tree-sortby-byte').disableTextSelection().click(function() {
		self.changeOptions({ treeSortBy: 'byte', treeSortRev: self._options.treeSortBy == 'byte' ? !self._options.treeSortRev : true });
	});
	$('#LeftColumn .tree-sortby-num').disableTextSelection().click(function() {
		self.changeOptions({ treeSortBy: 'num', treeSortRev: self._options.treeSortBy == 'num' ? !self._options.treeSortRev : true });
	});
	
	// Setup the directory tree, if a list was provided.
	if (this._options.directories) {
		this._tree = $('#DirectoryTree').tree({
			data: this._options.directories,
			root: this._options.settings.root,
			selection: function(e, hash) {
				self.changeOptions({ hash: hash });
			},
			comparator: function(a, b) {
				var modifier = self._options.treeSortRev ? -1 : 1;
				
				if (self._options.treeSortBy == 'byte') {
					if (a.totalbytes < b.totalbytes) return -1 * modifier;
					if (a.totalbytes > b.totalbytes) return 1 * modifier;
				}
				else if (self._options.treeSortBy == 'num') {
					if (a.totalnum < b.totalnum) return -1 * modifier;
					if (a.totalnum > b.totalnum) return 1 * modifier;
				}
				else {
					if (a.name.toLowerCase() < b.name.toLowerCase()) return -1 * modifier;
					if (a.name.toLowerCase() > b.name.toLowerCase()) return 1 * modifier;
				}
				
				// Secondary sort
				if (a.name.toLowerCase() < b.name.toLowerCase()) return -1;
				if (a.name.toLowerCase() > b.name.toLowerCase()) return 1;
				return 0;
			}
		});
	}
	
	this._lastHash = null;
	this._lastSection = null;
	this._lastSectionOptions = null;
	this._lastTreeOptions = null;
	
	this._sections = $('#Sections');
	this._filesSection = $('#Section_Files');
	this._typesSection = $('#Section_Types');
	this._sizesSection = $('#Section_Sizes');
	this._modifiedSection = $('#Section_Modified');
	this._subdirsSection = $('#Section_SubDirs');
	this._top100Section = $('#Section_Top100');
};

$.extend(Viewer.prototype, {
	
	_data: null,
	
	defaults: {
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
	
	changeOptions: function(opts, skipHistory) {
		
		$.extend(this._options, opts);
		
		if (!skipHistory) {
			dhtmlHistory.add(this._createLocation(opts), null);
		}
		
		this.display();
	},
	
	display: function(location, completeFn) {
		var self = this;
		
		if ($.isFunction(location)) {
			completeFn = location;
			location = null;
		}
		
		if ($.isString(location)) {
			this._processLocation(location);
		}
		
		// View the root hash if hash is not set.
		if (!this._options.hash) {
			this._options.hash = this._options.settings.root;
		}
		
		var sectionChanged = this._lastSection != this._options.section;
		this._lastSection = this._options.section;
		
		if (this._lastHash != this._options.hash) {
			if (this._xhr) this._xhr.abort();
			this._xhr = $.ajax({
				cache: false,
				url: document.reportsBaseURL + this._options.report + '/' + this._options.hash,
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
					
					if (self._options.directories) {
						self._tree.tree('select', self._options.hash);
					}
				},
				complete: function() {
					self._lastHash = self._options.hash;
					self._lastSection = self._lastSectionOptions = null;
					
					$('#Error')[self._data ? 'hide' : 'show']();
					$('#RightColumn')[self._data ? 'show' : 'hide']();
					
					// Scroll to the top of the report.
					$('#Report').get(0).scrollTop = 0;
					
					// Layout the left and right columns.
					self._display();
					
					// Correct the height of divs.
					document.resizeWindow();
					
					if ($.isFunction(completeFn)) {
						completeFn();
					}
				},
				success: function(data, status, xhr) {
					// Set the loaded data.
					self._data = data;
					
					if (self._options.directories) {
						// Determine the path to the selected directory.
						var hashPath = [];
						for (var i = 0; i < data.parents.length; i++) {
							hashPath.push(data.parents[i].hash);
						}
						hashPath.push(self._options.hash);
						
						// Load that path in the tree.
						self._tree.tree('select', hashPath);
					}
				}
			});
		}
		else {
			this._display();
			
			if (sectionChanged) {
				// Scroll to the top of the report.
				$('#Report').get(0).scrollTop = 0;
			}
			
			if ($.isFunction(completeFn)) {
				completeFn();
			}
		}
	},
	
	_sorter: function(tbody, column, reverse) {
		$('> tr', tbody).sortElements(function(a, b){
			var a = $('td:eq(' + column + ')', a);
			var b = $('td:eq(' + column + ')', b);
			
			var aVal = a.data('sortvalue') != null ? a.data('sortvalue') : a.text();
			var bVal = b.data('sortvalue') != null ? b.data('sortvalue') : b.text();
			
			return (aVal > bVal ? 1 : -1) * (reverse ? -1 : 1);
		});
	},
	
	_display: function() {
		if (this._data) {
			$('#Path').empty();
			for (var i = 0; i < this._data.parents.length; i++) {
				$('#Path').append($('<a>').attr('href', '#' + this._createLocation({ hash: this._data.parents[i].hash })).text(this._data.parents[i].name)).append(' ' + this._options.settings.ds.htmlencode() + ' ');
			}
			$('#Path').append(this._data.name.htmlencode());
			
			$('#Bytes').text(FormatBytes(this._data.bytes)); // + ' (' + AddCommas(this._data.bytes) + ')');
			$('#TotalBytes').text(FormatBytes(this._data.totalbytes)); // + ' (' + AddCommas(this._data.totalbytes) + ')');
			$('#Num').text(AddCommas(this._data.num));
			$('#TotalNum').text(AddCommas(this._data.totalnum));
			
			$('#Section_Message').hide().text('');
			
			$('#Sections')
				.removeClass('totals-sortedby-label totals-sortedby-byte totals-sortedby-num files-sortedby-name files-sortedby-type files-sortedby-size files-sortedby-modified top100-sortedby-name top100-sortedby-type top100-sortedby-size top100-sortedby-modified top100-sortedby-path')
				.addClass('totals-sortedby-' + this._options.totalsSortBy + ' files-sortedby-' + this._options.filesSortBy + ' top100-sortedby-' + this._options.top100SortBy)
				[(this._options.totalsSortRev ? 'add' : 'remove') + 'Class']('totals-sortrev')
				[(this._options.filesSortRev ? 'add' : 'remove') + 'Class']('files-sortrev')
				[(this._options.top100SortRev ? 'add' : 'remove') + 'Class']('top100-sortrev');
			
			if (this._lastSectionOptions != ''.concat(this._options.section, this._options.totalsSortBy, this._options.totalsSortRev, this._options.filesSortBy, this._options.filesSortRev, this._options.top100SortBy, this._options.top100SortRev)) {
				this._lastSectionOptions = ''.concat(this._options.section, this._options.totalsSortBy, this._options.totalsSortRev, this._options.filesSortBy, this._options.filesSortRev, this._options.top100SortBy, this._options.top100SortRev)
				
				switch (this._options.section) {
					case 'modified':
						this._displayModified();
						break;
					case 'types':
						this._displayTypes();
						break;
					case 'sizes':
						this._displaySizes();
						break;
					case 'files':
						this._displayFiles();
						break;
					case 'top100':
						this._displayTop100();
						break;
					default:
						this._displaySubDirs();
				}
			}
		}
		
		$('#LeftColumn')
			.removeClass('tree-sortedby-label tree-sortedby-byte tree-sortedby-num')
			.addClass('tree-sortedby-' + this._options.treeSortBy)
			[(this._options.treeSortRev ? 'add' : 'remove') + 'Class']('tree-sortrev');
		
		if (this._options.directories && this._lastTreeOptions != ''.concat(this._options.treeSortBy, this._options.treeSortRev)) {
			this._lastTreeOptions = ''.concat(this._options.treeSortBy, this._options.treeSortRev);
			this._tree.tree('resort');
		}
	},
	
	_displaySubDirs: function() {
		var self = this;
		
		$('> div', this._sections).hide();
		
		var subdirs = this._data.subdirs.slice(0);
		subdirs.push({ isfiles: true, totalbytes: this._data.bytes, totalnum: this._data.num });
		
		if (this._data.totalnum == 0 && this._data.subdirs.length == 0) {
			$('#Section_Message').text('Neither this directory nor its sub directories contain files.').show();
		}
		else {
			this._displayTotalsTable($('#SubDirs'), subdirs, function(data, field) {
				switch (field) {
					case 'label':
						if (data.isfiles) return '<i>Files in this Directory</i>';
						return '<a href="#' + self._createLocation({ hash: data.hash }).htmlencode() + '">' + data.name.htmlencode() + '</a>';
					case 'sortlabel':
						if (data.isfiles) return '';
						return data.name.toLowerCase();
					case 'bytes':
						return data.totalbytes;
					case 'num':
						return data.totalnum;
				}
			}, true);
			this._subdirsSection.show();
		}
		
		$('#Tabs li').removeClass('selected');
		$('#Tab_SubDirs').addClass('selected');
	},
	
	_displayModified: function() {
		var self = this;
		
		$('> div', this._sections).hide();
		
		if (!this._data.modified) {
			$('#Section_Message').text('This information is not available at this directory depth.').show();
		}
		else if (this._data.modified.length == 0) {
			$('#Section_Message').text('Neither this directory nor its sub directories contain files.').show();
		}
		else {
			this._displayTotalsTable($('#Modified'), this._data.modified, function(data, field, key) {
				switch (field) {
					case 'label':
						return self._options.settings.modified[key].label;
					case 'sortlabel':
						return parseInt(key);
					case 'bytes':
						return data[0];
					case 'num':
						return data[1];
				}
			});
			this._modifiedSection.show();
		}
		
		$('#Tabs li').removeClass('selected');
		$('#Tab_Modified').addClass('selected');
	},
	
	_displayTypes: function() {
		var self = this;
		
		$('> div', this._sections).hide();
		
		if (!this._data.types) {
			$('#Section_Message').text('This information is not available at this directory depth.').show();
		}
		else if (this._data.types.length == 0) {
			$('#Section_Message').text('Neither this directory nor its sub directories contain files.').show();
		}
		else {
			this._displayTotalsTable($('#Types'), this._data.types, function(data, field, key) {
				switch (field) {
					case 'label':
						return key == '' ? '<i>Unknown</i>' : key.htmlencode();
					case 'sortlabel':
						return key.toLowerCase();
					case 'bytes':
						return data[0];
					case 'num':
						return data[1];
				}
			}, true);
			this._typesSection.show();
		}
		
		$('#Tabs li').removeClass('selected');
		$('#Tab_Types').addClass('selected');
	},
	
	_displaySizes: function() {
		var self = this;
		
		$('> div', this._sections).hide();
		
		if (!this._data.types) {
			$('#Section_Message').text('This information is not available at this directory depth.').show();
		}
		else if (this._data.sizes.length == 0) {
			$('#Section_Message').text('Neither this directory nor its sub directories contain files.').show();
		}
		else {
			this._displayTotalsTable($('#Sizes'), this._data.sizes, function(data, field, key) {
				switch (field) {
					case 'label':
						return self._options.settings.sizes[parseInt(key)].label;
					case 'sortlabel':
						return parseInt(key);
					case 'bytes':
						return data[0];
					case 'num':
						return data[1];
				}
			});
			this._sizesSection.show();
		}
		
		$('#Tabs li').removeClass('selected');
		$('#Tab_Sizes').addClass('selected');
	},
	
	_displayTotalsTable: function(table, data, getValue, htmlLabel) {
		
		var self = this;
		var tbody = $('> tbody', table);
		var tfoot = $('> tfoot', table);
		
		tbody.empty();
		
		var totalBytes = 0;
		var totalNum = 0;
		
		var rows = [];
		
		for (var key in data) {
			var label = getValue(data[key], 'label', key);
			var sortValue = sortLabel = getValue(data[key], 'sortlabel', key);
			var bytes = getValue(data[key], 'bytes', key);
			var num = getValue(data[key], 'num', key);
			
			totalBytes += parseInt(bytes);
			totalNum += parseInt(num);
			
			switch (this._options.totalsSortBy) {
				case 'byte':
					sortValue = parseInt(bytes);
					break;
				case 'num':
					sortValue = parseInt(num);
					break;
			}
			
			var html = '';
			
			html += '<td class="totals-col-label">' + (htmlLabel ? label : label.htmlencode()) + '</td>';
			
			var bytePerCent = parseInt(10000 * parseInt(bytes) / Math.max(1, parseInt(this._data.totalbytes))) / 100;
			var byteColorIndex = Math.max(1, Math.floor(this._options.gradient.length * bytePerCent / 100)) - 1;
			html += '<td class="totals-col-byte" align="right">' + FormatBytes(bytes) + '</td>';
			html += '<td class="totals-col-byte" align="right">' + bytePerCent.toFixed(2) + '%' + '</td>';
			html += '<td class="totals-col-byte"><div class="percentbar"><div style="width: '+ bytePerCent +'%; background-color: #' + this._options.gradient[byteColorIndex] + ';">&nbsp;</div></div></td>';
			
			var numPerCent = parseInt(10000 * parseInt(num) / Math.max(1, parseInt(this._data.totalnum))) / 100;
			var numColorIndex = Math.max(1, Math.floor(this._options.gradient.length * numPerCent / 100)) - 1;
			html += '<td class="totals-col-num" align="right">' + AddCommas(num) + '</td>';
			html += '<td class="totals-col-num" align="right">' + numPerCent.toFixed(2) + '%' + '</td>';
			html += '<td class="totals-col-num"><div class="percentbar"><div style="width: '+ numPerCent +'%; background-color: #' + this._options.gradient[numColorIndex] + ';">&nbsp;</div></div></td>';
			
			var index = BinarySearch(rows, [ sortValue, sortLabel ], function(needle, item, index) {
				var modifier = self._options.totalsSortRev ? -1 : 1;
				
				if (needle[0] < item[0]) return -1 * modifier;
				if (needle[0] > item[0]) return 1 * modifier;
				
				if (needle[1] < item[1]) return -1 * modifier;
				if (needle[1] > item[1]) return 1 * modifier;
				
				return 0;
			});
			
			if (index < 0) {
				rows.splice(Math.abs(index)-1, 0, [sortValue, sortLabel, html]);
			}
			else {
				rows.splice(index, 0, [sortValue, sortLabel, html]);
			}
		}
		
		var finalHTML = '';
		for (var i = 0; i < rows.length; i++) {
			finalHTML += '<tr class="' + (i % 2 == 0 ? 'odd' : 'even') + '">' + rows[i][2] + '</tr>';
		}
		
		tbody.html(finalHTML);
		
		$('td:eq(1)', tfoot).text(FormatBytes(totalBytes) + ' (' + AddCommas(totalBytes) + ')');
		$('td:eq(2)', tfoot).text(AddCommas(totalNum));
	},
	
	_displayFiles: function() {
		var self = this;
		
		$('> div', this._sections).hide();
		
		if (this._data.files.length == 0) {
			$('#Section_Message').text('This directory does not contain files.').show();
		}
		else {
			var tbody = $('#Files > tbody').empty();
			
			var totalBytes = 0;
			var totalNum = 0;
			var data = this._data.files;
			
			var rows = [];
			
			for (var key in data) {
				
				var extData = '', ext = data[key].name.toLowerCase().split('.');
				if (ext.length > 1) ext = (extData = ext[ext.length-1]).htmlencode();
				else ext = "<i>Unknown</i>";
				
				var sortValue = data[key].name.toLowerCase();
				switch (this._options.filesSortBy) {
					case 'type':
						sortValue = extData;
						break;
					case 'size':
						sortValue = parseInt(data[key].size);
						break;
					case 'modified':
						sortValue = data[key].date + ' ' + data[key].time;
						break;
				}
				
				var html = '';
				
				html += '<td>' + data[key].name.htmlencode() + '</td>';
				html += '<td align="center">' + ext + '</td>';
				html += '<td align="right">' + FormatBytes(data[key].size) + '</td>';
				html += '<td>' + data[key].date + ' ' + data[key].time + '</td>';
				
				var index = BinarySearch(rows, [ sortValue, data[key].name ], function(needle, item, index) {
					var modifier = self._options.filesSortRev ? -1 : 1;
					
					if (needle[0] < item[0]) return -1 * modifier;
					if (needle[0] > item[0]) return 1 * modifier;
					
					if (needle[1] < item[1]) return -1 * modifier;
					if (needle[1] > item[1]) return 1 * modifier;
					
					return 0;
				});
				
				if (index < 0) {
					rows.splice(Math.abs(index)-1, 0, [sortValue, data[key].name, html]);
				}
				else {
					rows.splice(index, 0, [sortValue, data[key].name, html]);
				}
			}
			
			var finalHTML = '';
			for (var i = 0; i < rows.length; i++) {
				finalHTML += '<tr class="' + (i % 2 == 0 ? 'odd' : 'even') + '">' + rows[i][2] + '</tr>';
			}
			
			tbody.html(finalHTML);
			
			this._filesSection.show();
		}
		
		$('#Tabs li').removeClass('selected');
		$('#Tab_Files').addClass('selected');
	},
	
	_displayTop100: function() {
		var self = this;
		
		$('> div', this._sections).hide();
		
		if (!this._data.top100) {
			$('#Section_Message').text('This information is not available at this directory depth.').show();
		}
		else if (this._data.top100.length == 0) {
			$('#Section_Message').text('Neither this directory nor its sub directories contain files.').show();
		}
		else {
			var tbody = $('#Top100 > tbody').empty();
			
			var totalBytes = 0;
			var totalNum = 0;
			var data = this._data.top100;
			
			var rows = [];
			
			for (var key in data) {
				
				var extData = '', ext = data[key].name.toLowerCase().split('.');
				if (ext.length > 1) ext = (extData = ext[ext.length-1]).htmlencode();
				else ext = "<i>Unknown</i>";
				
				var sortValue = data[key].name;
				switch (this._options.top100SortBy) {
					case 'type':
						sortValue = extData;
						break;
					case 'size':
						sortValue = parseInt(data[key].size);
						break;
					case 'modified':
						sortValue = data[key].date + ' ' + data[key].time;
						break;
					case 'path':
						sortValue = data[key].path;
						break;
				}
				
				var html = '';
				
				html += '<td>' + data[key].name.htmlencode() + '</td>';
				html += '<td align="center">' + ext + '</td>';
				html += '<td align="right">' + FormatBytes(data[key].size) + '</td>';
				html += '<td>' + data[key].date + ' ' + data[key].time + '</td>';
				html += '<td><a href="#' + this._createLocation({ hash: data[key].hash, section: 'files' }).htmlencode() + '">' + data[key].path.htmlencode() + '</a></td>';
				
				var index = BinarySearch(rows, [ sortValue, data[key].name ], function(needle, item, index) {
					var modifier = self._options.top100SortRev ? -1 : 1;
					
					if (needle[0] < item[0]) return -1 * modifier;
					if (needle[0] > item[0]) return 1 * modifier;
					
					if (needle[1] < item[1]) return -1 * modifier;
					if (needle[1] > item[1]) return 1 * modifier;
					
					return 0;
				});
				
				if (index < 0) {
					rows.splice(Math.abs(index)-1, 0, [sortValue, data[key].name, html]);
				}
				else {
					rows.splice(index, 0, [sortValue, data[key].name, html]);
				}
			}
			
			var finalHTML = '';
			for (var i = 0; i < rows.length; i++) {
				finalHTML += '<tr class="' + (i % 2 == 0 ? 'odd' : 'even') + '">' + rows[i][2] + '</tr>';
			}
			
			tbody.html(finalHTML);
			
			this._top100Section.show();
		}
		
		$('#Tabs li').removeClass('selected');
		$('#Tab_Top100').addClass('selected');
	},
	
	_processLocation: function(location) {
		// Make sure the location is an array.
		if ($.isString(location)) {
			location = location.parseQS();
		}
		
		// Reset options to defaults.
		$.extend(this._options, this.defaults);
	
		// Validate and set options.
		if (location.h && location.h.match(/^[a-f0-9]{32}$/i)) {
			this._options.hash = location.h.toLowerCase();
		}
		if (location.s && location.s.match(/^(subdirs|files|modified|types|sizes|top100)$/i)) {
			this._options.section = location.s.toLowerCase();
		}
		if (location.tsb && location.tsb.match(/^(label|byte|num)$/)) {
			this._options.totalsSortBy = location.tsb;
		}
		if (location.tsr && location.tsr.match(/^[01]$/)) {
			this._options.totalsSortRev = location.tsr == '1';
		}
		if (location.fsb && location.fsb.match(/^(name|type|size|modified)$/)) {
			this._options.filesSortBy = location.fsb;
		}
		if (location.fsr && location.fsr.match(/^[01]$/)) {
			this._options.filesSortRev = location.fsr == '1';
		}
		if (location.bsb && location.bsb.match(/^(name|type|size|modified|path)$/)) {
			this._options.top100SortBy = location.bsb;
		}
		if (location.bsr && location.bsr.match(/^[01]$/)) {
			this._options.top100SortRev = location.bsr == '1';
		}
		if (location.dsb && location.dsb.match(/^(name|byte|num)$/)) {
			this._options.treeSortBy = location.dsb;
		}
		if (location.dsr && location.dsr.match(/^[01]$/)) {
			this._options.treeSortRev = location.dsr == '1';
		}
	},
	
	_createLocation: function(options) {
		var opts = $.extend({}, this._options, options);
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
	}
	
});