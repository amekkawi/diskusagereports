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
	
	_ajaxErrorHandler: function(xhr, status, err) {
		var parts = [], self = this;
		switch (status) {
			case 'parsererror':
				if (this._ajaxStage == 'directories') {
					$('#Loading').append($('<div>').html(this.translate('parse_error_skip_dirs',
						$('<span class="link"></span>')
							.html(this.translate('parse_error_skip_dirs_link'))
							.click(function(){ self._skipDirectoryList.apply(self, arguments); return false; })
					)));
				}
				else {
					$('#Loading').append($('<div>').html(this.translate('parse_error')));
				}
				break;
			case 'timeout':
				if (this._ajaxStage == 'directories') {
					$('#Loading').append($('<div>').html(this.translate('timeout_error_skip_dirs',
						$('<span class="link"></span>')
							.html(this.translate('timeout_error_skip_dirs_link'))
							.click(function(){ self._skipDirectoryList.apply(self, arguments); return false; })
					)));
				}
				else {
					$('#Loading').append($('<div>').html(this.translate('timeout_error')));
				}
				break;
			case 'error':
				switch (xhr.status) {
					case 404:
						if (this._ajaxStage == 'directories') {
							$('#Loading').append($('<div>').html(this.translate('directory_notfound_error',
								$('<span class="link"></span>')
									.html(this.translate('directory_notfound_error_link'))
									.click(function(){ self._skipDirectoryList.apply(self, arguments); return false; })
							)));
						}
						else {
							$('#Loading').append($('<div>').html(this.translate('settings_notfound_error')));
						}
						break;
					case 401:
						$('#Loading').append($('<div>').html(this.translate('username_required')));
						break;
					default:
						if (this._ajaxStage == 'directories') {
							$('#Loading').append($('<div>').html(this.translate('directory_unknown_status_error',
								xhr.status,
								$('<span class="link"></span>')
									.html(this.translate('directory_unknown_status_error_link'))
									.click(function(){ self._skipDirectoryList.apply(self, arguments); return false; })
							)));
						}
						else {
							$('#Loading').append($('<div>').html(this.translate('unknown_status_error', xhr.status+'')));
						}
				}
				break;
			default:
				// Show 'not found' errors if it seems we are accessing the report via file:///
				if (!status && xhr.status == 0 && window.location.protocol == 'file:') {
					if (this._ajaxStage == 'directories') {
						$('#Loading').append($('<div>').html(this.translate('directory_notfound_error',
							$('<span class="link"></span>')
								.html(this.translate('directory_notfound_error_link'))
								.click(function(){ self._skipDirectoryList.apply(self, arguments); return false; })
						)));
					}
					else {
						$('#Loading').append($('<div>').html(this.translate('settings_notfound_error')));
					}
				}
				else {
					if (this._ajaxStage == 'directories') {
						$('#Loading').append($('<div>').html(this.translate('directory_unknown_status_error',
							!status ? 'N/A' : status,
							$('<span class="link"></span>')
								.html(this.translate('directory_unknown_status_error_link'))
								.click(function(){ self._skipDirectoryList.apply(self, arguments); return false; })
						)));
					}
					else {
						$('#Loading').append($('<div>').html(this.translate('unknown_status_error', !status ? 'N/A' : status)));
					}
				}
		}
	},
	
	_downloadSettings: function(suffixIndex) {
		var self = this;
		
		if ($.isUndefined(suffixIndex))
			suffixIndex = 0;
		
		// Allow the suffix to be a string.
		if ($.isString(this.suffix))
			this.suffix = [ this.suffix ];
		
		// Make sure the suffix list contains an empty suffix for old reports.
		if (jQuery.inArray("", this.suffix) == -1)
			this.suffix.push("");
		
		this._ajaxStage = 'settings';
		
		// Load the settings file.
		$.ajax({
			cache: false,
			url: this.reportsBaseURL + this.report + '/settings' + this.suffix[suffixIndex],
			type: 'GET',
			dataType: 'json',
			error: function() {
				if (++suffixIndex == self.suffix.length) {
					self._ajaxErrorHandler.apply(self, arguments);
				}
				else {
					// Try again without the suffix to support older reports.
					self._downloadSettings(suffixIndex);
				}
			},
			success: function(settings, status, xhr){
				self.settings = settings;
				
				// Make sure the suffix is set in the settings.
				if ($.isUndefined(self.settings.suffix))
					self.settings.suffix = self.suffix[suffixIndex];
				
				self._processSettings(settings);
			}
		});
	},
	
	_processSettings: function() {
		var self = this;
		
		this._languageChangeStatic('title');
		this._languageChangeStatic('footer');
		
		this._processErrors();
		
		// Disable for old browser versions.
		var disableTree = ($.browser.msie && $.browser.version.match(/^[67]\./) != null);
		
		if (this.settings.directorytree && !disableTree) {
			$('#Loading').html(this.translate('loading_directories'));
			
			this._debugTimeoutFn(function(){
				self._downloadDirectories();
			}, this._debugTimeout);
		}
		
		else {
			$('#LeftColumn, #LeftColumnResizer').hide();
			this._finalSetup(null);
		}
	},
	
	_processErrors: function() {
		var self = this, errors = [];
		if (this.settings.errors.length > 0) {
			
			var errorContents = $('#ErrorsDialog').dialog('contents');
			for (var i = 0; i < this.settings.errors.length; i++) {
				var detail = '', errorTitle = '';
				
				if ($.isString(this.settings.errors[i])) {
					errorTitle = [ 'unknown_processing_error', this.settings.errors[i] ];
				}
				else {
					switch (this.settings.errors[i][0]) {
						case 'invalidline':
							switch (this.settings.errors[i][1]) {
								case 'regex':
									errorTitle = [ 'invalidline_error_regex' ];
									detail += '<div style="overflow: auto; width: 100%;">'+ (this.settings.errors[i][2]+'').htmlencode() +'</div>';
									break;
								case 'maxlinelength':
									errorTitle = [ 'invalidline_error_maxlinelength', this.settings.errors[i][2].length ];
									detail += '<div style="overflow: auto; width: 100%;">'+ (this.settings.errors[i][2]+'').htmlencode() +'</div>';
									break;
								case 'columncount':
									errorTitle = [ 'invalidline_error_columncount', this.settings.errors[i][2].length ];
									detail += '<table class="styledtable" border="1" cellspacing="0" cellpadding="4"><tbody><tr class="odd">';
									for (var c = 0; c < this.settings.errors[i][2].length; c++) {
										detail += '<td>' + (this.settings.errors[i][2][c]+'').htmlencode() + '</td>';
									}
									detail += '</tr></tbody></table>';
									break;
								case 'column':
									errorTitle = [ 'invalidline_error_column', this.settings.errors[i][2] ];
									detail += '<table class="styledtable" border="1" cellspacing="0" cellpadding="4"><tbody><tr class="odd">';
									for (var c = 0; c < this.settings.errors[i][4].length; c++) {
										detail += '<td ' + (c == this.settings.errors[i][3] ? ' style="background-color: #FF0;"' : '') + '>' + (this.settings.errors[i][4][c]+'').htmlencode() + '</td>';
									}
									detail += '</tr></tbody></table>';
									break;
								default:
									errorTitle = [ 'invalidline_error_unknown', this.settings.errors[i][1] ];
							}
							break;
						case 'writefail':
							errorTitle = [ 'writefail_error', this.settings.errors[i][2] ];
							detail += '<div style="overflow: auto; width: 100%;">'+ (this.settings.errors[i][1]+'').htmlencode() +'</div>';
							break;
						case 'finderror':
							switch (this.settings.errors[i][1]) {
								case 'STAT_FAIL':
									errorTitle = [ 'find_stat_error' ];
									detail += '<div style="overflow: auto; width: 100%;">'+ (this.settings.errors[i][2]+'').htmlencode() +'</div>';
									break;
								case 'OPENDIR_FAIL':
									errorTitle = [ 'find_opendir_error' ];
									detail += '<div style="overflow: auto; width: 100%;">'+ (this.settings.errors[i][2]+'').htmlencode() +'</div>';
									break;
								default:
									errorTitle = [ 'find_unknown_error' ];
									detail += '<table class="styledtable" border="1" cellspacing="0" cellpadding="4"><tbody><tr class="odd">';
									for (var c = 1; c < this.settings.errors[i].length; c++) {
										detail += '<td>' + (this.settings.errors[i][c]+'').htmlencode() + '</td>';
									}
									detail += '</tr></tbody></table>';
							}
							break;
						default:
							errorTitle = [ 'unknown_error' ];
							detail += '<table class="styledtable" border="1" cellspacing="0" cellpadding="4"><tbody><tr class="odd">';
							for (var c = 0; c < this.settings.errors[i].length; c++) {
								detail += '<td>' + (this.settings.errors[i][c]+'').htmlencode() + '</td>';
							}
							detail += '</tr></tbody></table>';
					}
				}
				
				errors.push([ errorTitle, detail != '' ? detail : undefined ]);
			}
			
			this.reportErrors(errors);
		}
	},
	
	_downloadDirectories: function() {
		var self = this;
		
		this._timeout_skip = setTimeout(function(){
			$('#Loading').append($('<div>').html(self.translate('directory_skip',
				$('<span class="link"></span>')
					.html(self.translate('directory_skip_link'))
					.click(function(){ self._skipDirectoryList.apply(self, arguments); return false; })
			)));
		}, 5000);
		
		this._ajaxStage = 'directories';
		
		// Load the directory lookup file.
		this._xhr_directories = $.ajax({
			cache: false,
			url: this.reportsBaseURL + this.report + '/directories' + this.settings.suffix,
			type: 'GET',
			dataType: 'json',
			error: function() {
				// If directorytree is false then the user is asking to skip it.
				if (self.settings.directorytree)
					self._ajaxErrorHandler.apply(self, arguments);
			},
			success: function(directories, status, xhr){
				self.directories = directories;
				
				if ($.isUndefined(directories[self.settings.root])) {
					$('#Loading').append($('<div>').html(self.translate('directory_root_notfound',
						$('<span class="link"></span>')
							.html(self.translate('directory_root_notfound_link'))
							.click(function(){ self._skipDirectoryList.apply(self, arguments); return false; })
					)));
				}
				else if (self.settings.directorytree) {
					self._finalSetup();
				}
			},
			complete: function() {
				if (self._timeout_skip) clearTimeout(self._timeout_skip);
				$('#DirectoryListSkip').remove();
			}
		});
	},
	
	_skipDirectoryList: function() {
		this.settings.directorytree = false;
		if (this._xhr_directories) this._xhr_directories.abort();
		$('#LeftColumn, #LeftColumnResizer').hide();
		this._finalSetup(null);
	},
	
	_finalSetup: function() {
		var self = this;
		
		$('#Loading').html(this.translate('displaying_report'));
		
		this._debugTimeoutFn(function(){
			self._setupTabs();
			self._setupColHeaders();
			
			// Setup the directory tree, if it was downloaded.
			if (self.directories) {
				
				// Allow resizing of the left column.
				$('#LeftColumnResizer').disableTextSelection().mousedown(function(evDown){
					var startX = evDown.screenX,
						origWidth = $('#LeftColumn').width(),
						resizeTimeout, resizeCounter = 0, resizeFn = function(evMove){
							resizeCounter = 0;
							$('#LeftColumn').width(Math.max(100, Math.min($('#Columns').width() - 200, origWidth + evMove.screenX - startX)));
						};
					
					$(document).bind('mousemove.resizer', function(evMove){
						// Adjust heights when the window is resized.
						if (resizeCounter >= 250) {
							resizeFn(evMove);
						}
						else {
							if (resizeTimeout) clearTimeout(resizeTimeout);
							resizeTimeout = setTimeout(function() { resizeFn(evMove); }, resizeCounter += 75);
						}
					});
					
					$(document).one('mouseup', function(evUp){
						$(document).unbind('mousemove.resizer');
					});
				});
				
				self._tree = $('#DirectoryTree').tree({
					data: self.directories,
					root: self.settings.root,
					selection: function(e, hash, files) {
						if (files) self.setOptions({ hash: hash, section: 'files' });
						else self.setOptions({ hash: hash });
					},
					comparator: function(a, b) {
						
						// Skip if the options have not yet been set.
						if (self.options) {
							var modifier = self.options.treeSortRev ? -1 : 1;
							
							if (self.options.treeSortBy == 'byte') {
								if (parseInt(a.totalbytes) < parseInt(b.totalbytes)) return -1 * modifier;
								if (parseInt(a.totalbytes) > parseInt(b.totalbytes)) return 1 * modifier;
							}
							else if (self.options.treeSortBy == 'num') {
								if (parseInt(a.totalnum) < parseInt(b.totalnum)) return -1 * modifier;
								if (parseInt(a.totalnum) > parseInt(b.totalnum)) return 1 * modifier;
							}
							else if (a.files && !b.files) {
								return 1 * modifier;
							}
							else if (!a.files && b.files) {
								return -1 * modifier;
							}
							else if (a.files && b.files) {
								return 0;
							}
							else {
								if (a.name.toLowerCase() < b.name.toLowerCase()) return -1 * modifier;
								if (a.name.toLowerCase() > b.name.toLowerCase()) return 1 * modifier;
							}
						}
						
						// Secondary sort
						if (a.name.toLowerCase() < b.name.toLowerCase()) return -1;
						if (a.name.toLowerCase() > b.name.toLowerCase()) return 1;
						return 0;
					},
					getPrefix: function(data, parentdata) {
						if (!data || !parentdata || !self.options || self.options.treeSortBy == 'label') return null;
						
						var percent = data[self.options.treeSortBy == 'byte' ? 'totalbytes' : 'totalnum'] / parentdata[self.options.treeSortBy == 'byte' ? 'totalbytes' : 'totalnum'] * 100;
						if (percent < 1) return '<1%';
						else return Math.round(percent) + '%';
					}
				});
			}
			
			// Set up history handling.
			$.history.addHandler(function(token){
				self.setLocation(token);
			});
			
			// Set the starting location.
			self.setLocation($.history.getToken(), function(){
				$('#Loading').hide();
				$('#Columns').show();
				
				// Force a resize since the viewer is now displayed.
				self.resizeWindow();
				
				var selected = $('#DirectoryTree li.selected');
				if (selected.hasClass('ui-tree-root')) {
					$('#LeftColumnScroller').scrollTop(0).scrollLeft(0);
				}
				else {
					$('#LeftColumnScroller').scrollIntoView(selected);
				}
			});
			
		}, this._debugTimeout);
	},
	
	_setupTabs: function() {
		var self = this;
		
		$('#Tabs li').disableTextSelection();
		
		$('#Tab_SubDirs').click(function(){
			self.setOptions({ page: 1, 'section': 'subdirs' });
		});
		$('#Tab_Modified').click(function(){
			self.setOptions({ page: 1, 'section': 'modified' });
		});
		$('#Tab_Types').click(function(){
			self.setOptions({ page: 1, 'section': 'types' });
		});
		$('#Tab_Sizes').click(function(){
			self.setOptions({ page: 1, 'section': 'sizes' });
		});
		$('#Tab_Files').click(function(){
			self.setOptions({ page: 1, 'section': 'files' });
		});
		$('#Tab_Top100').click(function(){
			self.setOptions({ page: 1, 'section': 'top100' });
		});
	},
	
	_setupColHeaders: function() {
		var self = this;
		
		$('#Sections .totals-sortby-label').disableTextSelection().click(function() {
			self.setOptions({ page: 1, totalsSortBy: 'label', totalsSortRev: self.options.totalsSortBy == 'label' ? !self.options.totalsSortRev : false });
		});
		$('#Sections .totals-sortby-byte').disableTextSelection().click(function() {
			self.setOptions({ page: 1, totalsSortBy: 'byte', totalsSortRev: self.options.totalsSortBy == 'byte' ? !self.options.totalsSortRev : true });
		});
		$('#Sections .totals-sortby-num').disableTextSelection().click(function() {
			self.setOptions({ page: 1, totalsSortBy: 'num', totalsSortRev: self.options.totalsSortBy == 'num' ? !self.options.totalsSortRev : true });
		});

		$('#Files-SortBy-name').disableTextSelection().click(function() {
			self.setOptions({ page: 1, filesSortBy: 'name', filesSortRev: self.options.filesSortBy == 'name' ? !self.options.filesSortRev : false });
		});
		$('#Files-SortBy-type').disableTextSelection().click(function() {
			self.setOptions({ page: 1, filesSortBy: 'type', filesSortRev: self.options.filesSortBy == 'type' ? !self.options.filesSortRev : false });
		});
		$('#Files-SortBy-size').disableTextSelection().click(function() {
			self.setOptions({ page: 1, filesSortBy: 'size', filesSortRev: self.options.filesSortBy == 'size' ? !self.options.filesSortRev : true });
		});
		$('#Files-SortBy-modified').disableTextSelection().click(function() {
			self.setOptions({ page: 1, filesSortBy: 'modified', filesSortRev: self.options.filesSortBy == 'modified' ? !self.options.filesSortRev : false });
		});

		$('#Top100-SortBy-name').disableTextSelection().click(function() {
			self.setOptions({ top100SortBy: 'name', top100SortRev: self.options.top100SortBy == 'name' ? !self.options.top100SortRev : false });
		});
		$('#Top100-SortBy-type').disableTextSelection().click(function() {
			self.setOptions({ top100SortBy: 'type', top100SortRev: self.options.top100SortBy == 'type' ? !self.options.top100SortRev : false });
		});
		$('#Top100-SortBy-size').disableTextSelection().click(function() {
			self.setOptions({ top100SortBy: 'size', top100SortRev: self.options.top100SortBy == 'size' ? !self.options.top100SortRev : true });
		});
		$('#Top100-SortBy-modified').disableTextSelection().click(function() {
			self.setOptions({ top100SortBy: 'modified', top100SortRev: self.options.top100SortBy == 'modified' ? !self.options.top100SortRev : false });
		});
		$('#Top100-SortBy-path').disableTextSelection().click(function() {
			self.setOptions({ top100SortBy: 'path', top100SortRev: self.options.top100SortBy == 'path' ? !self.options.top100SortRev : false });
		});
		
		$('#LeftColumn .tree-sortby-label').disableTextSelection().click(function() {
			self.setOptions({ treeSortBy: 'label', treeSortRev: self.options.treeSortBy == 'label' ? !self.options.treeSortRev : false });
		});
		$('#LeftColumn .tree-sortby-byte').disableTextSelection().click(function() {
			self.setOptions({ treeSortBy: 'byte', treeSortRev: self.options.treeSortBy == 'byte' ? !self.options.treeSortRev : true });
		});
		$('#LeftColumn .tree-sortby-num').disableTextSelection().click(function() {
			self.setOptions({ treeSortBy: 'num', treeSortRev: self.options.treeSortBy == 'num' ? !self.options.treeSortRev : true });
		});
	}
	
});

})(jQuery);