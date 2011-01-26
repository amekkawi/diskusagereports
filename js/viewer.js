/**
 * @author amekkawi
 */

var Viewer = function(opts) {
	// Set the inital options.
	this._options = $.extend({}, this.defaults, opts);
	
	this._lastHash = null;
	
	this._sections = $('#Sections');
	this._filesSection = $('#Section_Files');
	this._typesSection = $('#Section_Types');
	this._sizesSection = $('#Section_Sizes');
	this._modifiedSection = $('#Section_Modified');
	this._subdirsSection = $('#Section_SubDirs');
	
	this._filesBody = $('#Files tbody');
	this._typesBody = $('#Types tbody');
	this._sizesBody = $('#Sizes tbody');
	this._modifiedBody = $('#Modified tbody');
	this._subdirsBody = $('#SubDirs tbody');
};

$.extend(Viewer.prototype, {
	
	_data: null,
	
	defaults: {
		settings: null,
		report: null,
		hash: null,
		section: 'subdirs',
		totalsSortBy: 'label',
		totalsSortRev: false,
		filesSortBy: 'name',
		filesSortRev: false
	},
	
	display: function(location, completeFn) {
		var self = this;
		
		if ($.isFunction(location)) {
			completeFn = location;
			location = null;
		}
		
		if ($.isString(location)) {
			location = location.parseQS();
		}
		
		// Validate and set options.
		if (location.h && location.h.match(/^[a-f0-9]{32}$/i)) {
			this._options.hash = location.h.toLowerCase();
		}
		if (location.s && location.s.match(/^(subdirs|files|modified|types|sizes)$/i)) {
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
		
		// View the root hash if hash is not set.
		if (!this._options.hash) {
			this._options.hash = this._options.settings.root;
		}
		
		if (this._lastHash != this._options.hash) {
			$.ajax({
				cache: false,
				url: document.reportsBaseURL + this._options.report + '/' + this._options.hash,
				type: 'GET',
				dataType: 'json',
				success: function(data, status, xhr) {
					self._lastHash = self._options.hash;
					self._data = data;
					self._display();
					
					if ($.isFunction(completeFn)) {
						completeFn();
					}
				}
			});
		}
		else {
			this._display();
			
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
		$('#Path').empty();
		for (var i = 0; i < this._data.parents.length; i++) {
			$('#Path').append($('<a>').attr('href', '#' + this._createLocation({ hash: this._data.parents[i].hash })).text(this._data.parents[i].name)).append(this._options.settings.ds.htmlencode());
		}
		$('#Path').append(this._data.name.htmlencode());
		
		$('#Bytes').text(FormatBytes(this._data.bytes) + ' (' + AddCommas(this._data.bytes) + ')');
		$('#TotalBytes').text(FormatBytes(this._data.totalbytes) + ' (' + AddCommas(this._data.totalbytes) + ')');
		$('#Num').text(AddCommas(this._data.num));
		$('#TotalNum').text(AddCommas(this._data.totalnum));
		
		this._displaySubDirs();
		this._displayModified();
		this._displayTypes();
		this._displaySizes();
		this._displayFiles();
	},
	
	_displaySubDirs: function() {
		var self = this;
		this._displayTotalsTable($('#SubDirs'), this._data.subdirs, function(data, field) {
			switch (field) {
				case 'label':
					return '<a href="#' + self._createLocation({ hash: data.hash }).htmlencode() + '">' + data.name.htmlencode() + '</a>';
				case 'sortlabel':
					return data.name;
				case 'bytes':
					return data.totalbytes;
				case 'num':
					return data.totalnum;
			}
		}, true);
		
		//$('> div', this._sections).hide();
		//this._subdirsSection.show();
	},
	
	_displayModified: function() {
		var self = this;
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
		}, true);
		
		//$('> div', this._sections).hide();
		//this._subdirsSection.show();
	},
	
	_displayTypes: function() {
		var self = this;
		this._displayTotalsTable($('#Types'), this._data.types, function(data, field, key) {
			switch (field) {
				case 'label':
				case 'sortlabel':
					return key;
				case 'bytes':
					return data[0];
				case 'num':
					return data[1];
			}
		}, true);
		
		//$('> div', this._sections).hide();
		//this._subdirsSection.show();
	},
	
	_displaySizes: function() {
		var self = this;
		this._displayTotalsTable($('#Sizes'), this._data.sizes, function(data, field, key) {
			switch (field) {
				case 'label':
					return self._options.settings.sizes[parseInt(key)].label;
				case 'sortlabel':
					return key;
				case 'bytes':
					return data[0];
				case 'num':
					return data[1];
			}
		}, true);
		
		//$('> div', this._sections).hide();
		//this._subdirsSection.show();
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
			var sortValue = sortLabel = getValue(data[key], 'sortlabel', key)
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
			
			html += '<tr>';
			
			html += '<td>' + (htmlLabel ? label : label.htmlencode()) + '</td>';
			
			html += '<td align="right">' + FormatBytes(bytes) + '</td>';
			html += '<td align="right">' + (100 * parseInt(bytes) / parseInt(this._data.totalbytes)).toFixed(2) + '%' + '</td>';
			html += '<td style="width: 100px;"><div style="overflow: hidden; width: '+ (100 * parseInt(bytes) / parseInt(this._data.totalbytes)) +'%; background-color: #0CF;">&nbsp;</div></td>';
			
			html += '<td align="right">' + AddCommas(num) + '</td>';
			html += '<td align="right">' + (100 * parseInt(num) / parseInt(this._data.totalnum)).toFixed(2) + '%' + '</td>';
			html += '<td style="width: 100px;"><div style="overflow: hidden; width: '+ (100 * parseInt(num) / parseInt(this._data.totalnum)) +'%; background-color: #0CF;">&nbsp;</div></td>';
			
			html += '</tr>';
			
			var index = BinarySearch(rows, [ sortValue, sortLabel ], function(needle, item, index) {
				var modifier = self._options.totalsSortRev ? -1 : 1;
				
				if (self._options.totalsSortBy != 'label') modifier *= -1;
				
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
			finalHTML += rows[i][2];
		}
		
		tbody.html(finalHTML);
		
		$('td:eq(1)', tfoot).text(FormatBytes(totalBytes) + ' (' + AddCommas(totalBytes) + ')');
		$('td:eq(2)', tfoot).text(AddCommas(totalNum));
	},
	
	_displayFiles: function() {
		var self = this;
		var tbody = $('#Files > tbody').empty();
		
		var totalBytes = 0;
		var totalNum = 0;
		var data = this._data.files;
		
		var rows = [];
		
		for (var key in data) {
			
			var extData = '', ext = data[key].name.split('.');
			if (ext.length > 1) ext = (extData = ext[ext.length-1]).htmlencode();
			else ext = "<i>None</i>";
			
			var sortValue = data[key].name;
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
			
			html += '<tr>';
			
			html += '<td>' + data[key].name.htmlencode() + '</td>';
			html += '<td align="center">' + ext + '</td>';
			html += '<td align="right">' + FormatBytes(data[key].size) + '</td>';
			html += '<td>' + data[key].date + ' ' + data[key].time + '</td>';
			
			html += '</tr>';
			
			var index = BinarySearch(rows, [ sortValue, data[key].name ], function(needle, item, index) {
				var modifier = self._options.filesSortRev ? -1 : 1;
				
				if (self._options.totalsSortBy == 'size') modifier *= -1;
				
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
			finalHTML += rows[i][2];
		}
		
		tbody.html(finalHTML);
	},
	
	_createLocation: function(options) {
		var opts = $.extend({}, this._options, options);
		return 'h=' + escape(opts.hash)
			+ '&s=' + escape(opts.section)
			+ '&tsb=' + escape(opts.totalsSortBy)
			+ '&tsr=' + escape(opts.totalsSortRev ? '1' : '0')
			+ '&fsb=' + escape(opts.filesSortBy)
			+ '&fsr=' + escape(opts.filesSortRev ? '1' : '0');
	},
	
	setLocation: function(location) {
		// Make sure the location is an array.
		if ($.isString(location)) location = location.parseQS();
		
		if (location.report) {
			this._report = report;
		}
	}
	
});