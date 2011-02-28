;(function($){

$.extend(Controller.prototype, {
	
	_ajaxErrorHandler: function(xhr, status, err) {
		var parts = [], self = this;
		switch (status) {
			case 'parsererror':
				parts.push('Error: Data is invalid or could not be parsed. ');
				if (this._ajaxStage == 'directories') {
					parts.push($('<span class="link">Skip the Directory List</span>').click(function(){ self._skipDirectoryList.apply(self, arguments); return false; }));
				}
				break;
			case 'timeout':
				parts.push('Error: Download took to long and timed out. Reload to try again');
				if (this._ajaxStage == 'directories') {
					parts.push(' or ');
					parts.push($('<span class="link">skip the directory list</span>').click(function(){ self._skipDirectoryList.apply(self, arguments); return false; }));
				}
				parts.push('.');
				break;
			case 'error':
				switch (xhr.status) {
					case 404:
						parts.push('Error: Not found. ');
						if (this._ajaxStage == 'directories') {
							parts.push('The directory list may not exist. ');
							if (this._ajaxStage == 'directories') {
								parts.push($('<span class="link">Skip the Directory List</span>').click(function(){ self._skipDirectoryList.apply(self, arguments); return false; }));
							}
						}
						else {
							parts.push('The report may not exist.');
						}
						break;
					case 401:
						parts.push('Error: A username and password is required. Reload to try again.');
						break;
					default:
						parts.push('Error: An unknown error occurred (' + xhr.status + '). Reload to try again');
						if (this._ajaxStage == 'directories') {
							parts.push(' or ');
							parts.push($('<span class="link">skip the directory list</span>').click(function(){ self._skipDirectoryList.apply(self, arguments); return false; }));
						}
						parts.push('.');
						$.dumpWindow(xhr);
				}
				break;
			default:
				parts.push('Error: An unknown error occurred. Reload to try again');
				if (this._ajaxStage == 'directories') {
					parts.push(' or ');
					parts.push($('<span class="link">skip the directory list</span>').click(function(){ self._skipDirectoryList.apply(self, arguments); return false; }));
				}
				parts.push('.');
		}
		
		var newMsg = $('<div>').appendTo($('#Loading'));
		for (var i = 0; i < parts.length; i++) {
			newMsg.append(parts[i]);
		}
	},
	
	_downloadSettings: function() {
		var self = this;
		
		this._ajaxStage = 'settings';
		
		// Load the settings file.
		$.ajax({
			cache: false,
			url: this.reportsBaseURL + this.report + '/settings',
			type: 'GET',
			dataType: 'json',
			error: function() { self._ajaxErrorHandler.apply(self, arguments); },
			success: function(settings, status, xhr){
				self.settings = settings;
				self._processSettings(settings);
			}
		});
	},
	
	_processSettings: function() {
		var self = this;
		
		if (this.settings.name) {
			$('#Title > span').append(' for: ').append($('<b>').text(this.settings.name));
		}
		
		$('#Created').text(' on ' + this.settings.created);
		
		this._processErrors();
		
		if (this.settings.directorytree) {
			$('#Loading').text('Loading Directory List for Report...');
			
			setTimeout(function(){
				self._downloadDirectories();
			}, this._debugTimeout);
		}
		
		else {
			$('#LeftColumn, #LeftColumnResizer').hide();
			this._finalSetup(null);
		}
	},
	
	_processErrors: function() {
		if (this.settings.errors.length > 0) {
			
			// Add a button that displays errors counts and shows error messages on click.
			$('#ErrorCount')
				.text('Errors: ' + this.settings.errors.length)
				.click(function() {
					$('#Errors, #Dimmer').show();
					self.resizeWindow();
				})
				.disableTextSelection()
				.show();
			
			// Handler for "Close" button on error window.
			$('#ErrorsTitle div').click(function() {
				$('#Errors, #Dimmer').hide();
			});
			
			// Populate the error list.
			for (var i = 0; i < this.settings.errors.length; i++) {
				var detail = '',
					errorTitle = 'Unknown Error (' + this.settings.errors[i] + ')';
				
				switch (this.settings.errors[i][0]) {
					case 'invalidline':
						errorTitle = 'Invalid Line - ';
						switch (this.settings.errors[i][1]) {
							case 'regex':
								errorTitle += 'Wrong Format';
								detail += '<div style="overflow: auto; width: 100%;">'+ this.settings.errors[i][2].htmlencode() +'</div>';
								break;
							case 'maxlinelength':
								errorTitle += 'Line Too Long (' + this.settings.errors[i][2].length + ' characters)';
								detail += '<div style="overflow: auto; width: 100%;">'+ this.settings.errors[i][2].htmlencode() +'</div>';
								break;
							case 'columncount':
								errorTitle += 'Wrong Column Count (' + this.settings.errors[i][2].length + ')';
								detail += '<table class="styledtable" border="1" cellspacing="0" cellpadding="4"><tbody><tr class="odd">';
								for (var c = 0; c < this.settings.errors[i][2].length; c++) {
									detail += '<td>' + this.settings.errors[i][2][c].htmlencode() + '</td>';
								}
								detail += '</tr></tbody></table>';
								break;
							case 'column':
								errorTitle += 'Bad Value for Column \'' + this.settings.errors[i][2] + '\'';
								detail += '<table class="styledtable" border="1" cellspacing="0" cellpadding="4"><tbody><tr class="odd">';
								for (var c = 0; c < this.settings.errors[i][4].length; c++) {
									detail += '<td ' + (c == this.settings.errors[i][3] ? ' style="background-color: #FF0;"' : '') + '>' + this.settings.errors[i][4][c].htmlencode() + '</td>';
								}
								detail += '</tr></tbody></table>';
								break;
							default:
								errorTitle += 'Unknown Error (' + this.settings.errors[i][1] + ')';
						}
						errorTitle += ':';
						break;
					case 'writefail':
						errorTitle = 'Error Writing File (' + this.settings.errors[i][2].htmlencode() + ') for:'
						detail += '<div style="overflow: auto; width: 100%;">'+ this.settings.errors[i][1].htmlencode() +'</div>';
						break;
				}
				var errorItem = $('<div>').addClass('errors-item').html('<b>' + errorTitle.htmlencode() + '</b>').appendTo($('#ErrorsList'));
				if (detail != '') errorItem.append(detail);
			}
		}
	},
	
	_downloadDirectories: function() {
		var self = this;
		
		this._timeout_skip = setTimeout(function(){
			$('#Loading').append(
				$('<div id="DirectoryListSkip"></div>')
					.text('Taking too long? ')
					.append(
						$('<span class="link">Skip the Directory List</span>')
						.click(function(){ self._skipDirectoryList.apply(self, arguments); return false; })
					)
			);
		}, 5000);
		
		this._ajaxStage = 'directories';
		
		// Load the directory lookup file.
		this._xhr_directories = $.ajax({
			cache: false,
			url: this.reportsBaseURL + this.report + '/directories',
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
					$('#Loading').append($('<div>').text('Error: The root directory could not be found in the directory list.'));
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
		
		$('#Loading').text('Displaying Report...');
		
		setTimeout(function(){ // Debugging timeout
			self._setupTabs();
			self._setupColHeaders();
			
			// Setup the directory tree, if it was downloaded.
			if (self.directories) {
				
				// Allow resizing of the left column.
				$('#LeftColumnResizer').disableTextSelection().mousedown(function(evDown){
					var startX = evDown.screenX;
					var origWidth = $('#LeftColumn').width();
					
					$(document).bind('mousemove.resizer', function(evMove){
						$('#LeftColumn').width(Math.max(100, Math.min($('#Columns').width() - 200, origWidth + evMove.screenX - startX)));
						
						if (document.timeout_resize) clearTimeout(document.timeout_resize);
						document.timeout_resize = setTimeout(function() {
							self.resizeWindow();
						}, 100);
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
			$(document).bind('dhtmlhistory', function(e, location, data) {
				self.setLocation(location);
			});
			
			// Set the starting location.
			self.setLocation(dhtmlHistory.getCurrentLocation(), function(){
				$('#Loading').hide();
				$('#Columns').show();
				
				// Force a resize since the viewer is now displayed.
				self.resizeWindow();
				
				$('#LeftColumnScroller').scrollIntoView($('#DirectoryTree li.selected'));
			});
			
			// Debug
			return;
			
			document.viewer = new Viewer({
				directories: directories,
				settings: self.settings,
				report: window.location.search.substring(1)
			});
			
			document.viewer.display(dhtmlHistory.getCurrentLocation(), function(){
				$('#Loading').hide();
				$('#Columns').show();
				
				// Force a resize since the viewer is now displayed.
				self.resizeWindow();
				
				$('#LeftColumnScroller').scrollIntoView($('#DirectoryTree li.selected'));
			});
		
		}, this._debugTimeout); // Debugging timeout
	},
	
	_setupTabs: function() {
		var self = this;
		
		$('#Tabs li').disableTextSelection();
		
		$('#Tab_SubDirs').click(function(){
			self.setOptions({ 'section': 'subdirs' });
		});
		$('#Tab_Modified').click(function(){
			self.setOptions({ 'section': 'modified' });
		});
		$('#Tab_Types').click(function(){
			self.setOptions({ 'section': 'types' });
		});
		$('#Tab_Sizes').click(function(){
			self.setOptions({ 'section': 'sizes' });
		});
		$('#Tab_Files').click(function(){
			self.setOptions({ 'section': 'files' });
		});
		$('#Tab_Top100').click(function(){
			self.setOptions({ 'section': 'top100' });
		});
	},
	
	_setupColHeaders: function() {
		var self = this;
		
		$('#Sections .totals-sortby-label').disableTextSelection().click(function() {
			self.setOptions({ totalsSortBy: 'label', totalsSortRev: self.options.totalsSortBy == 'label' ? !self.options.totalsSortRev : false });
		});
		$('#Sections .totals-sortby-byte').disableTextSelection().click(function() {
			self.setOptions({ totalsSortBy: 'byte', totalsSortRev: self.options.totalsSortBy == 'byte' ? !self.options.totalsSortRev : true });
		});
		$('#Sections .totals-sortby-num').disableTextSelection().click(function() {
			self.setOptions({ totalsSortBy: 'num', totalsSortRev: self.options.totalsSortBy == 'num' ? !self.options.totalsSortRev : true });
		});

		$('#Files-SortBy-name').disableTextSelection().click(function() {
			self.setOptions({ filesSortBy: 'name', filesSortRev: self.options.filesSortBy == 'name' ? !self.options.filesSortRev : false });
		});
		$('#Files-SortBy-type').disableTextSelection().click(function() {
			self.setOptions({ filesSortBy: 'type', filesSortRev: self.options.filesSortBy == 'type' ? !self.options.filesSortRev : false });
		});
		$('#Files-SortBy-size').disableTextSelection().click(function() {
			self.setOptions({ filesSortBy: 'size', filesSortRev: self.options.filesSortBy == 'size' ? !self.options.filesSortRev : true });
		});
		$('#Files-SortBy-modified').disableTextSelection().click(function() {
			self.setOptions({ filesSortBy: 'modified', filesSortRev: self.options.filesSortBy == 'modified' ? !self.options.filesSortRev : false });
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