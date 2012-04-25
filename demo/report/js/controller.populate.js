/* 
 * Copyright (c) 2011 André Mekkawi <diskusage@andremekkawi.com>
 * Version: $Source Version$
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

;(function($){

$.extend(Controller.prototype, {
	
	_populateReport: function(force) {
		this._languageChangeStatic('title');
		
		if (this._data) {
			if (force || this._lastSectionOptions != ''.concat(this.options.section, this.options.totalsSortBy, this.options.totalsSortRev, this.options.filesSortBy, this.options.filesSortRev, this.options.top100SortBy, this.options.top100SortRev, this.options.page)) {
				this._lastSectionOptions = ''.concat(this.options.section, this.options.totalsSortBy, this.options.totalsSortRev, this.options.filesSortBy, this.options.filesSortRev, this.options.top100SortBy, this.options.top100SortRev, this.options.page);
				
				var fullPath = '';
				$('#Path').empty();
				
				for (var i = 0; i < this._data.parents.length; i++) {
					var pathLink = $('<a>')
						.attr('href', '#' + this._createLocation({ hash: this._data.parents[i].hash }, 'path'))
						.text(this._data.parents[i].name)
						.appendTo('#Path');
					
					if (this._data.parents[i].name == this.settings.ds) {
						pathLink.addClass('root');
					}
					else {
						$('#Path').append('<span>' + this.settings.ds + '</span>');
					}
					
					fullPath += (fullPath == '' ? '' : this.settings.ds) + this._data.parents[i].name;
				}
				$('#Path').append(this._data.name.htmlencode());
				
				var summary =
					this.translate('total_size', FormatBytes(this._data.totalbytes), FormatBytes(this._data.bytes))
					+ '<br/>'
					+ this.translate('total_files', AddCommas(this._data.totalnum), AddCommas(this._data.num));
				
				if (this.settings.path) {
					summary +=
						'<br/>'
						+ this.translate('full_path', 
								this.settings.path + (this.settings.path == this.settings.ds ? '' : this.settings.ds) + fullPath
								+ (fullPath == '' ? '' : this.settings.ds) + this._data.name
							);
				}
				
				$('#DirSummary').html(summary);
				
				$('#Sections')
					.removeClass('totals-sortedby-label totals-sortedby-byte totals-sortedby-num files-sortedby-name files-sortedby-type files-sortedby-size files-sortedby-modified top100-sortedby-name top100-sortedby-type top100-sortedby-size top100-sortedby-modified top100-sortedby-path')
					.addClass('totals-sortedby-' + this.options.totalsSortBy + ' files-sortedby-' + this.options.filesSortBy + ' top100-sortedby-' + this.options.top100SortBy)
					[(this.options.totalsSortRev ? 'add' : 'remove') + 'Class']('totals-sortrev')
					[(this.options.filesSortRev ? 'add' : 'remove') + 'Class']('files-sortrev')
					[(this.options.top100SortRev ? 'add' : 'remove') + 'Class']('top100-sortrev');
				
				$('#Section_Message').hide().text('');
				
				switch (this.options.section) {
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
			
			// Scroll to the top of the section if it changed.
			if (this._lastSection != this.options.section) {
				this._lastSection = this.options.section;
				$('#Report').get(0).scrollTop = 0;
			}
		}
		
		if (this.directories && this._lastTreeOptions != ''.concat(this.options.treeSortBy, this.options.treeSortRev)) {
			$('#LeftColumn')
				.removeClass('tree-sortedby-label tree-sortedby-byte tree-sortedby-num')
				.addClass('tree-sortedby-' + this.options.treeSortBy)
				[(this.options.treeSortRev ? 'add' : 'remove') + 'Class']('tree-sortrev');
			
			this._lastTreeOptions = ''.concat(this.options.treeSortBy, this.options.treeSortRev);
			this._tree.tree('resort');
		}
	},
	
	_displaySubDirs: function() {
		var self = this;
		
		$('> div', this._sections).hide();
		
		var subdirs = this._data.subdirs.slice(0);
		subdirs.push({ isfiles: true, totalbytes: this._data.bytes, totalnum: this._data.num });
		
		if (parseInt(this._data.totalnum) == 0 && this._data.subdirs.length == 0) {
			$('#Section_Message').html(this.translate('notice_no_files_incl_sub')).show();
		}
		else {
			this._displayTotalsTable($('#SubDirs'), subdirs, function(data, field) {
				switch (field) {
					case 'label':
						if (data.isfiles) return '<i>' + self.translate('contents_files_in_this_directory') + '</i>';
						return '<a href="#' + self._createLocation({ hash: data.hash }, 'contents').htmlencode() + '">' + data.name.htmlencode() + '</a>';
					case 'sortlabel':
						if (data.isfiles) return '';
						return data.name.toLowerCase();
					case 'bytes':
						return parseInt(data.totalbytes);
					case 'num':
						return parseInt(data.totalnum);
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
			$('#Section_Message').html(this.translate('notice_no_info_at_depth')).show();
		}
		else if (this._data.modified.length == 0) {
			$('#Section_Message').html(this.translate('notice_no_files_incl_sub')).show();
		}
		else {
			this._displayTotalsTable($('#Modified'), this._data.modified, function(data, field, key) {
				switch (field) {
					case 'label':
						return self.settings.modified[key].label;
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
			$('#Section_Message').html(this.translate('notice_no_info_at_depth')).show();
		}
		else if (this._data.types.length == 0) {
			$('#Section_Message').html(this.translate('notice_no_files_incl_sub')).show();
		}
		else {
			this._displayTotalsTable($('#Types'), this._data.types, function(data, field, key) {
				switch (field) {
					case 'label':
						return key == '' ? '<i>' + self.translate('unknown_file_type') + '</i>' : key.htmlencode();
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
			$('#Section_Message').html(this.translate('notice_no_info_at_depth')).show();
		}
		else if (this._data.sizes.length == 0) {
			$('#Section_Message').html(this.translate('notice_no_files_incl_sub')).show();
		}
		else {
			this._displayTotalsTable($('#Sizes'), this._data.sizes, function(data, field, key) {
				switch (field) {
					case 'label':
						return self.settings.sizes[parseInt(key)].label;
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
			
			switch (this.options.totalsSortBy) {
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
			var byteColorIndex = Math.max(1, Math.floor(this.gradient.length * bytePerCent / 100)) - 1;
			html += '<td class="totals-col-byte" align="right">' + FormatBytes(bytes) + '</td>';
			html += '<td class="totals-col-byte" align="right">' + bytePerCent.toFixed(2) + '%' + '</td>';
			html += '<td class="totals-col-byte"><div class="percentbar"><div style="width: '+ bytePerCent +'%; background-color: #' + this.gradient[byteColorIndex] + ';">&nbsp;</div></div></td>';
			
			var numPerCent = parseInt(10000 * parseInt(num) / Math.max(1, parseInt(this._data.totalnum))) / 100;
			var numColorIndex = Math.max(1, Math.floor(this.gradient.length * numPerCent / 100)) - 1;
			html += '<td class="totals-col-num" align="right">' + AddCommas(num) + '</td>';
			html += '<td class="totals-col-num" align="right">' + numPerCent.toFixed(2) + '%' + '</td>';
			html += '<td class="totals-col-num"><div class="percentbar"><div style="width: '+ numPerCent +'%; background-color: #' + this.gradient[numColorIndex] + ';">&nbsp;</div></div></td>';
			
			var index = BinarySearch(rows, [ sortValue, sortLabel ], function(needle, item, index) {
				var modifier = self.options.totalsSortRev ? -1 : 1;
				
				if (needle[0] < item[0]) return -1 * modifier;
				if (needle[0] > item[0]) return 1 * modifier;
				
				if (needle[1] < item[1]) return -1;
				if (needle[1] > item[1]) return 1;
				
				return 0;
			});
			
			if (index < 0) {
				rows.splice(Math.abs(index)-1, 0, [sortValue, sortLabel, html]);
			}
			else {
				rows.splice(index, 0, [sortValue, sortLabel, html]);
			}
		}
		
		// Determine the rows that will be shown (if not all of them).
		var iStart = 0, iEnd = rows.length;
		if (rows.length > this.pageMax) {
			iStart = (this.options.page - 1) * this.pageMax;
			iEnd = Math.min(rows.length, this.options.page * this.pageMax);
			this._displayPager(rows.length);
		}
		
		// Create the final HTML for the report.
		var finalHTML = '';
		for (var i = iStart; i < iEnd; i++) {
			finalHTML += '<tr class="' + (i % 2 == 0 ? 'odd' : 'even') + '">' + rows[i][2] + '</tr>';
		}
		
		// Display the report.
		tbody.html(finalHTML);
		
		// Add the totals to the footer.
		$('td:eq(1)', tfoot).text(FormatBytes(totalBytes) + ' (' + AddCommas(totalBytes) + ')');
		$('td:eq(2)', tfoot).text(AddCommas(totalNum));
	},
	
	_displayPager: function(length) {
		$('.pager').show();
		
		var lastPage = Math.ceil(length / this.pageMax);
		
		// Make sure the current page is valid.
		this.options.page = Math.max(1, Math.min(lastPage, this.options.page));
		
		$('.pager-prev')
			[ this.options.page == 1 ? 'addClass' : 'removeClass' ]('disabled')
			.attr('href', '#' + this._createLocation({ page: Math.max(1, this.options.page - 1) }, 'pager'));
		
		$('.pager-next')
			[ this.options.page == lastPage ? 'addClass' : 'removeClass' ]('disabled')
			.attr('href', '#' + this._createLocation({ page: Math.min(lastPage, this.options.page + 1) }, 'pager'));
		
		$('.pager-range').html(this.translate('displayed_rows', (this.options.page - 1) * this.pageMax + 1, Math.min(length, this.options.page * this.pageMax), length));
		
		$('.pager-pages').html('<span>' + this.translate('page_list_label') + ':</span>');
		
		// Add a link for the first page.
		if (this.options.page > 5) {
			$('.pager-pages').append($('<a href="#' + this._createLocation({ page: 1 }, 'pager').htmlencode() + '">1</a>'));
			if (this.options.page > 6) $('.pager-pages').append($('<span>...</span>'));
		}
		
		// Add links for the pages near the current one.
		for (var i = Math.max(1, this.options.page - 4); i <= Math.min(this.options.page + 4, lastPage); i++) {
			$('.pager-pages').append($('<a class="' + (this.options.page == i ? 'selected' : '') + '" href="#' + this._createLocation({ page: i }, 'pager').htmlencode() + '">' + i + '</a>'));
		}
		
		// Add a link for the last page.
		if (this.options.page < lastPage - 4) {
			if (this.options.page < lastPage - 5) $('.pager-pages').append($('<span>...</span>'));
			$('.pager-pages').append($('<a href="#' + this._createLocation({ page: lastPage }, 'pager').htmlencode() + '">' + lastPage + '</a>'));
		}
	},
	
	_displayFiles: function() {
		var self = this;
		
		$('> div', this._sections).hide();
		
		if (this._data.files.length == 0) {
			$('#Section_Message').html(this.translate('notice_no_files')).show();
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
				else ext = "<i>" + this.translate('unknown_file_type') + "</i>";
				
				var sortValue = data[key].name.toLowerCase();
				switch (this.options.filesSortBy) {
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
				
				var index = BinarySearch(rows, [ sortValue, data[key].name.toLowerCase() ], function(needle, item, index) {
					var modifier = self.options.filesSortRev ? -1 : 1;
					
					if (needle[0] < item[0]) return -1 * modifier;
					if (needle[0] > item[0]) return 1 * modifier;
					
					if (needle[1] < item[1]) return -1;
					if (needle[1] > item[1]) return 1;
					
					return 0;
				});
				
				if (index < 0) {
					rows.splice(Math.abs(index)-1, 0, [sortValue, data[key].name.toLowerCase(), html]);
				}
				else {
					rows.splice(index, 0, [sortValue, data[key].name.toLowerCase(), html]);
				}
			}
			
			// Determine the rows that will be shown (if not all of them).
			var iStart = 0, iEnd = rows.length;
			if (rows.length > this.pageMax) {
				iStart = (this.options.page - 1) * this.pageMax;
				iEnd = Math.min(rows.length, this.options.page * this.pageMax);
				this._displayPager(rows.length);
			}
			
			var finalHTML = '';
			for (var i = iStart; i < iEnd; i++) {
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
			$('#Section_Message').html(this.translate('notice_no_info_at_depth')).show();
		}
		else if (this._data.top100.length == 0) {
			$('#Section_Message').html(this.translate('notice_no_files_incl_sub')).show();
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
				
				var sortValue = data[key].name.toLowerCase();
				switch (this.options.top100SortBy) {
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
				html += '<td><a href="#' + this._createLocation({ hash: data[key].hash, section: 'files' }, 'top100').htmlencode() + '">' + data[key].path.replace(new RegExp(RegExp.escape(this.settings.ds), 'g'), this.settings.ds+' ').htmlencode() + '</a></td>';
				
				var index = BinarySearch(rows, [ sortValue, data[key].name ], function(needle, item, index) {
					var modifier = self.options.top100SortRev ? -1 : 1;
					
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
			
			// Determine the rows that will be shown (if not all of them).
			var iStart = 0, iEnd = rows.length;
			if (rows.length > this.pageMax) {
				iStart = (this.options.page - 1) * this.pageMax;
				iEnd = Math.min(rows.length, this.options.page * this.pageMax);
				this._displayPager(rows.length);
			}
			
			var finalHTML = '';
			for (var i = iStart; i < iEnd; i++) {
				finalHTML += '<tr class="' + (i % 2 == 0 ? 'odd' : 'even') + '">' + rows[i][2] + '</tr>';
			}
			
			tbody.html(finalHTML);
			
			this._top100Section.show();
		}
		
		$('#Tabs li').removeClass('selected');
		$('#Tab_Top100').addClass('selected');
	}
	
});

})(jQuery);