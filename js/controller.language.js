;(function($){

$.extend(Controller.prototype, {
	
	// The default language, if the requested language is not supported.
	defaultLanguage: 'en-us',
	
	// The current language.
	language: null,
	
	// The language data.
	_languages: { },
	
	translate: function() {
		//$.dumpWindow(arguments);
		arguments = $.makeArray(arguments);
		
		var key = arguments.shift(),
			str = this._languages[this.language][key];
		
		if (!$.isString(str)) {
			// TODO: Throw an error because the key does not exist.
		}
		else {
			str = str.toUpperCase();
			
			for (var i = 0; i < arguments.length; i++) {
				if (str.indexOf('{' + (i+1) + '}') < 0) {
					// TODO: Throw an error because {N} does not exist.
				}
				else {
					str = str.replace(new RegExp(RegExp.escape('{' + (i+1) + '}'), 'g'), arguments[i]);
				}
			}
			
			return str;
		}
	},
	
	addLanguage: function(lang) {
		if ($.isUndefined(this._languages[lang.toLowerCase()]))
			this._languages[lang.toLowerCase()] = 'load';
	},
	
	setLanguage: function(lang) {
		var self = this;
		
		lang = lang.toLowerCase();
		
		// Throw an error if the language is not supported.
		if (!this._languages[lang]) {
			// TODO
		}
		else {
			// TODO: Support 'Accept-Language' header syntax.
			this.language = lang;
			
			// Retrieve the language data if it has not been loaded.
			if (this._languages[lang] == 'load') {
				$.ajax({
					url: 'lang/' + lang + '.json',
					async: false,
					dataType: 'json',
					error: function(xhr, msg, ex) {
						alert("Failed to load (" + msg + "): " + lang);
					},
					success: function(data) {
						self._languages[lang] = data;
						self._languageChangeStatic();
					}
				});
			}
			else {
				this._languageChangeStatic();
			}
		}
	},
	
	_languageLoad: function() {
		this.addLanguage(this.defaultLanguage);
		if (!this.language) this.setLanguage(this.defaultLanguage);
	},
	
	_languageChangeStatic: function(part) {
		if (!part || part == 'title') {
			if (this.settings && this.settings.name) {
				$('#Title').html(this.translate('title_with_name', this.settings.name.htmlencode()));
			}
			else {
				$('#Title').html(this.translate('title'));
			}
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
				'<a target="_blank" href="http://diskusagereport.sourceforge.net/">Disk Usage Reports</a>',
				this._languages[this.language].language_name,
				this.settings ? this.settings.created.htmlencode() : null
			));
		}
	}
});

})(jQuery);