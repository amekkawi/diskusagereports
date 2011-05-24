/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereport.sf.net/license.html
 */

;(function($){

$.extend(Controller.prototype, {
	
	// The default language, if the requested language is not supported.
	defaultLanguage: 'en-us',
	
	// The current language.
	language: null,
	
	// The language data.
	_languages: { },
	
	translate: function() {
		arguments = $.makeArray(arguments);
		
		var key = arguments.shift(),
			str = this._languages[this.language][key],
			parts = [], isTextOnly = true,
			argIndex, lastIndex = 0, match, re = new RegExp('{([0-9])}', 'g');
		
		// Throw an error because the key does not exist.
		if (!$.isString(str)) {
			throw "'" + key + "' does not exist in language file.";
		}
		else {
			// TODO: Remove toUpperCase() debug code.
			//str = str.toUpperCase();
			
			// Find all replacements in the string.
			while (match = re.exec(str)) {
				
				// Add any text between the last match and this one.
				if (lastIndex < match.index) {
					parts.push(document.createTextNode(str.substring(lastIndex, match.index)));
				}
				
				argIndex = parseInt(match[1]) - 1;
				
				if ($.isUndefined(arguments[argIndex])) {
					throw 'Replacement not passed to Controller.translate() for ' + match[0] + ' in ' + key;
				}
				else if ($.isString(arguments[argIndex]) || $.isNumber(arguments[argIndex])) {
					parts.push(document.createTextNode(arguments[argIndex]+''));
				}
				else if (arguments[argIndex].toArray) {
					parts.push.apply(parts, arguments[argIndex].toArray());
					isTextOnly = false;
				}
				else {
					throw 'Invalid argument for Controller.translate(). Must be string or jQuery object.';
				}
				
				lastIndex = re.lastIndex;
			}
			
			if (lastIndex != str.length) {
				parts.push(document.createTextNode(str.substr(lastIndex)));
			}
			
			if (isTextOnly) {
				var retStr = '';
				for (var i = 0; i < parts.length; i++) {
					retStr += parts[i].textContent;
				}
				return retStr;
			}
			else {			
				return $(parts);
			}
		}
	},
	
	isLanguageSupported: function(lang) {
		return $.isDefined(this._languages[lang.toLowerCase()]);
	},
	
	addLanguage: function(lang) {
		if ($.isUndefined(this._languages[lang.toLowerCase()]))
			this._languages[lang.toLowerCase()] = 'load';
	},
	
	setLanguage: function(lang) {
		var self = this, result = true;
		
		lang = lang.toLowerCase();
		
		// Mark the language as unsupported.
		if (!this.isLanguageSupported(lang)) {
			result = "Unsupported language: " + lang;
		}
		else {
			// TODO: Support 'Accept-Language' header syntax by processing lang to fall back from something like 'eng-us' to 'eng'.
			
			// Retrieve the language data if it has not been loaded.
			if (this._languages[lang] == 'load') {
				$.ajax({
					url: 'lang/' + lang + '.json',
					async: false,
					dataType: 'json',
					error: function(xhr, msg, ex) {
						result = "Failed to load language file (" + msg + "): " + 'lang/' + lang + '.json';
					},
					success: function(data) {
						self.language = lang;
						self._languages[lang] = data;
						self._languageChangeStatic();
					}
				});
			}
			else {
				this._languageChangeStatic();
			}
		}
		
		return result;
	},
	
	_languageChangeStatic: function(part) {
		if (!part || part == 'title') {
			if (this.settings && this.settings.name) {
				$('#Title').html(this.translate('title_with_name', $('<b>').text(this.settings.name))).show();
				document.title = this.translate('title_with_name', this.settings.name);
			}
			else {
				$('#Title').html(this.translate('title')).show();
				document.title = this.translate('title');
			}
		}
		
		if (!part || part == 'errors') {
			$('#ErrorsTitle span').html(this.translate('errors_title'));
			$('#ErrorsTitle div').html(this.translate('errors_close'));
		}
		
		if (!part || part == 'tree') {
			$('#TreeSort .tree-sortby-label span').html(this.translate('tree_header_name'));
			$('#TreeSort .tree-sortby-byte span').html(this.translate('tree_header_size'));
			$('#TreeSort .tree-sortby-num span').html(this.translate('tree_header_count'));
			$('#DirectoryTree li.ui-tree-files span.ui-tree-label').html(this.translate('tree_files_in_this_directory'));
		}
		
		if (!part || part == 'tabs') {
			$('#Tab_SubDirs').html(this.translate('tab_contents'));
			$('#Tab_Files').html(this.translate('tab_file_list'));
			$('#Tab_Modified').html(this.translate('tab_last_modified'));
			$('#Tab_Sizes').html(this.translate('tab_file_sizes'));
			$('#Tab_Types').html(this.translate('tab_file_types'));
			$('#Tab_Top100').html(this.translate('tab_top_100'));
		}
		
		if (!part || part == 'pager') {
			$('#Sections .pager-prev').html(this.translate('prev_page_button'));
			$('#Sections .pager-next').html(this.translate('next_page_button'));
		}
		
		if (!part || part == 'sections') {
			$('#SubDirs > thead .totals-sortby-label span').html(this.translate('contents_header_name'));
			$('#SubDirs > thead .totals-sortby-byte span').html(this.translate('contents_header_total_size'));
			$('#SubDirs > thead .totals-sortby-num span').html(this.translate('contents_header_file_count'));
			$('#SubDirs > tfoot td:eq(0)').html(this.translate('table_footer_total'));
			
			$('#Files-SortBy-name span').html(this.translate('file_list_header_name'));
			$('#Files-SortBy-type span').html(this.translate('file_list_header_type'));
			$('#Files-SortBy-size span').html(this.translate('file_list_header_size'));
			$('#Files-SortBy-modified span').html(this.translate('file_list_header_modified'));
			
			$('#Modified > thead .totals-sortby-label span').html(this.translate('last_modified_header_age'));
			$('#Modified > thead .totals-sortby-byte span').html(this.translate('last_modified_header_total_size'));
			$('#Modified > thead .totals-sortby-num span').html(this.translate('last_modified_header_file_count'));
			$('#Modified > tfoot td:eq(0)').html(this.translate('table_footer_total'));
			
			$('#Sizes > thead .totals-sortby-label span').html(this.translate('file_sizes_header_range'));
			$('#Sizes > thead .totals-sortby-byte span').html(this.translate('file_sizes_header_total_size'));
			$('#Sizes > thead .totals-sortby-num span').html(this.translate('file_sizes_header_file_count'));
			$('#Sizes > tfoot td:eq(0)').html(this.translate('table_footer_total'));
			
			$('#Types > thead .totals-sortby-label span').html(this.translate('file_types_header_extension'));
			$('#Types > thead .totals-sortby-byte span').html(this.translate('file_types_header_total_size'));
			$('#Types > thead .totals-sortby-num span').html(this.translate('file_types_header_file_count'));
			$('#Types > tfoot td:eq(0)').html(this.translate('table_footer_total'));
			
			$('#Top100-SortBy-name span').html(this.translate('top_100_header_name'));
			$('#Top100-SortBy-type span').html(this.translate('top_100_header_type'));
			$('#Top100-SortBy-size span').html(this.translate('top_100_header_size'));
			$('#Top100-SortBy-modified span').html(this.translate('top_100_header_modified'));
			$('#Top100-SortBy-path span').html(this.translate('top_100_header_path'));
		}
		
		if (!part || part == 'footer') {
			$('#Footer').html(this.translate(this.settings ? 'footer_with_created' : 'footer',
				this.settings ? this.settings.created.htmlencode() : null,
				$('<a target="_blank" href="http://diskusagereport.sourceforge.net/">Disk Usage Reports</a>'),
				this._languages[this.language].language_name
			));
		}
		
		// Resize window in case the header was previously hidden.
		this.resizeWindow();
	}
});

})(jQuery);