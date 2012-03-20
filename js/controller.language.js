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

Controller.prototype.inits.push(function() {
	var self = this;
	
	$('#ChangeLanguage').live('click', function(){
		$('#LanguageDialog').dialog('open');
	});
	
	$('#LanguageDialog').dialog({
		opening: function(e, contents) {
			contents.empty();
			
			var langs = self.getSupportedLanguages(true);
			
			var ul = $('<ul>');
			$.each(langs, function(i, lang) {
				$('<a href="#"></a>').click(function(e){
					e.preventDefault();
					self.setLanguage(lang);
					$('#LanguageDialog').dialog('close');
				}).text(self.getLanguageName(lang)).appendTo($('<li>').appendTo(ul));
			});
			
			ul.appendTo(contents);
		},
		backgroundColor: '#FFF'
	});
});

$.extend(Controller.prototype, {
	
	// The default language, if the requested language is not supported.
	defaultLanguage: 'en-us',
	
	// The current language.
	language: null,
	
	// The language data.
	_languages: { },
	
	translate: function() {
		arguments = $.makeArray(arguments);
		
		if (!this.isLanguageLoaded(this.language)) {
			this.reportError("The language file for '" + this.language + "' has not been loaded.");
			return '';
		}
		
		var key = arguments.shift(),
			translation = this._languages[this.language][key],
			parts = [], isTextOnly = true,
			argIndex, lastIndex = 0, match, re = new RegExp('{([0-9])}', 'g');
		
		// Throw an error because the key does not exist.
		if (!$.isString(translation)) {
			this.reportError("A translation for '" + key + "' does not exist.");
			return '';
		}
		else {
			// Find all replacements in the string.
			while (match = re.exec(translation)) {
				
				// Add any text between the last match and this one.
				if (lastIndex < match.index) {
					parts.push(document.createTextNode(translation.substring(lastIndex, match.index)));
				}
				
				argIndex = parseInt(match[1]) - 1;
				
				if ($.isUndefined(arguments[argIndex]) || arguments[argIndex] == null) {
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
			
			if (lastIndex != translation.length) {
				parts.push(document.createTextNode(translation.substr(lastIndex)));
			}
			
			if (isTextOnly) {
				return $(parts).text();
			}
			else {			
				return $(parts);
			}
		}
	},
	
	isSupportedLanguage: function(lang) {
		return lang && $.isDefined(this._languages[lang.toLowerCase()]);
	},
	
	isLanguageLoaded: function(lang) {
		return this.isSupportedLanguage(lang) && !$.isString(this._languages[lang]);
	},
	
	addLanguage: function(lang, name) {
		if ($.isString(lang)) lang = [ [ lang, name ] ];
		
		for (var i = 0; i < lang.length; i++)
			if ($.isUndefined(this._languages[lang[i][0].toLowerCase()]))
				this._languages[lang[i][0].toLowerCase()] = lang[i][1];
	},
	
	setLanguage: function(rules, returnFn) {
		var self = this,
			matches = this.matchSupportedLanguagesToRules(rules);
		
		// Set the lang to the first supported langauge match.
		// Otherwise set it to the language default.
		var lang = matches.length ? matches[0].lang : this.defaultLanguage;
		
		// Retrieve the language data if it has not been loaded.
		if (this._preLoad) {
			this.language = lang;
		}
		else {
			this.loadLanguage(lang, function(ret, message){
				if (ret) {
					self.language = lang;
					self._languageChangeStatic();
					self._populateReport(true);
				}
				
				if ($.isFunction(returnFn))
					returnFn(ret, message);
			});
		}
	},
	
	loadLanguage: function(lang, returnFn) {
		var self = this;
		
		// Return that the language is unsupported.
		if (!this.isSupportedLanguage(lang)) {
			if ($.isFunction(returnFn))
				returnFn(false, 'Unsupported language: ' + lang);
		}
		
		// Retrieve the language data if it has not been loaded.
		else if ($.isString(this._languages[lang])) {
			try {
				if (this._langXHR) this._langXHR.abort();
				this._langXHR = $.ajax({
					url: 'lang/' + lang + '.txt',
					dataType: 'json',
					error: function(xhr, status, ex) {
						if (status == 'abort') return;
						
						var msg;
						switch (status) {
							case 'parsererror':
								msg = 'Invalid JSON syntax:';
								break;
							case 'timeout':
								msg = 'Timed out while downloading';
								break;
							case 'error':
								switch (xhr.status) {
									case 404:
										msg = '404 Not Found:';
										break;
									case 401:
										msg = '401 Unauthorized:';
										break;
									default:
										msg = 'HTTP Status ' + xhr.status + ':';
								}
								break;
							default:
								msg = 'Unknown Error';
						}
						
						if ($.isFunction(returnFn))
							returnFn(false, msg + ' lang/' + lang + '.txt');
					},
					success: function(data, status, xhr) {
						if (data) {
							if ($.isString(data['extends'])) {
								self.loadLanguage(data['extends'], function(ret, message){
									self._languages[lang] = $.extend({}, self._languages[data['extends']], data);
									returnFn(ret, message);
								});
							}
							else {
								if ($.isFunction(returnFn))
									self._languages[lang] = data;
									returnFn(true);
							}
						}
					}
				});
			}
			catch (e) {
				// TODO: Handle exception when data is viewed via 'file:///' protocol.
				if ($.isFunction(returnFn))
					returnFn(false, 'AJAX exception: lang/' + lang + '.txt');
			}
		}
		
		// The data has already been loaded.
		else {
			if ($.isFunction(returnFn))
				returnFn(true);
		}
	},
	
	_languageChangeStatic: function(part) {
		if (!part || part == 'title') {
			var titleArgs = [ 'title' ], doctitleArgs = [ 'doctitle' ];
			
			if (this.settings && this.settings.name) {
				titleArgs[0] += '_with_name';
				titleArgs.push($('<b>').text(this.settings.name));
				
				doctitleArgs[0] += '_with_name';
				doctitleArgs.push(this.settings.name);
			}
			
			if (this._data && this._data.name) {
				doctitleArgs[0] += '_with_dir';
				doctitleArgs.push(this._data.name);
			}
			
			$('#Title').html(this.translate.apply(this, titleArgs)).show();
			document.title = this.translate.apply(this, doctitleArgs);
		}
		
		if (!part || part == 'errors') {
			var errorsCount = this.translate('errors_button', '<span>' + this._errors.length + '</span>'),
				errorsDialog = this.translate('errors_title');
			$('#ErrorCount').html(errorsCount == '' ? 'Errors: <span>' + this._errors.length + '</span>' : errorsCount);
			$('#ErrorsDialog').dialog('option', 'title', errorsDialog == '' ? 'Errors:' : errorsDialog);
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
				$('<span id="ChangeLanguage"></span>').text(this.getLanguageName(this.language))
			));
		}
		
		if (!part || part == 'language') {
			$('#LanguageDialog').dialog('option', 'title', this.translate('languagedialog_title'));
		}
		
		// Resize window in case the header was previously hidden.
		this.resizeWindow();
	},
	
	getSupportedLanguages: function(sorted, includeHidden) {
		
		var self = this, list = $.getArrayKeys(this._languages);
		
		// Remove hidden.
		if (!includeHidden) {
			for (var i = list.length - 1; i >= 0; i--) {
				if (this._languages[list[i]].hidden)
					list.splice(i, 1);
			}
		}
		
		// Sort the list in alpha order.
		if (sorted) {
			list.sort(function(a,b){
				var aname = self.getLanguageName(a),
					bname = self.getLanguageName(b);
				
				if (aname == null && bname == null) return 0;
				else if (aname == null) return 1;
				else if (bname == null) return -1;
				else if (aname.toLowerCase() > bname.toLowerCase()) return 1;
				else if (aname.toLowerCase() < bname.toLowerCase()) return -1;
				else return 0;
			});
		}
		
		return list;
	},
	
	getLanguageName: function(lang) {
		return this.isLanguageLoaded(lang) ? this._languages[lang].language_name : this._languages[lang];
	},
	
	parseAcceptLanguage: function(rules) {
		// Remove whitespace and split into rules.
		rules = rules.toLowerCase().replace(/\s/g, '').split(',');
		
		for (var i = rules.length - 1; i >= 0; i--) {
			var split = rules[i].match(/^(([a-z]{1,8})(-([a-z]{1,8}))?)(;q=((1(\.[0]{1,3})?)|(0(\.[0-9]{1,3})?)))?$/i);
			// 1 = full language tag
			// 2 = primary language tag
			// 4 = secondary language tag
			// 6 = quality
			
			if (split) {
				rules[i] = { order: i, full: split[1], primary: split[2], secondary: split[4] ? split[4] : null, quality: isNaN(split[6]) ? 1 : parseFloat(split[6]) };
			}
			else {
				rules.splice(i, 1);
			}
		}
		
		this.sortLanguageRules(rules);
		
		return rules;
	},
	
	sortLanguageRules: function(rules) {
		rules.sort(function(a,b){
			var acomp = a.lang ? a.lang == a.full ? 0 : 1 : 0;
			var bcomp = b.lang ? b.lang == b.full ? 0 : 1 : 0;
			if (a.order == b.order && acomp != bcomp) {
				return acomp - bcomp;
			}
			else if (a.quality == 0 || b.quality == 0 || a.quality == b.quality) {
				return a.order  - b.order;
			}
			else {
				return b.quality - a.quality;
			}
		});
	},
	
	parseLanguageTag: function(lang) {
		var match = lang.replace(/\s/g, '').match(/^(([a-z]{1,8})(-([a-z]{1,8}))?)$/i);
		if (match)
			return { full: match[1], primary: match[2], secondary: match[4] ? match[4] : null };
		else
			return false;
	},
	
	determineRuleForLanguage: function(rules, lang) {
		var langMatch = this.parseLanguageTag(lang),
			ret = false;
		
		if (langMatch !== false) {
			for (var i = 0; i < rules.length; i++) {
				// The rule is an exact match.
				if (rules[i].full == langMatch.full) {
					return rules[i];
				}
				
				// The primary part of the language tag matches.
				else if (rules[i].primary == langMatch.primary) {
					// Our first partial match. 
					if (ret === false) {
						ret = rules[i];
					}
					// Only override the current partial match if it is more general.
					// Ex: For the lang 'en-xx', 'en' would override 'en-yy', but not vise vera.
					else if (ret.secondary != null && rules[i].secondary == null) {
						ret = rules[i];
					}
				}
			}
		}
		return ret;
	},
	
	matchSupportedLanguagesToRules: function(rules) {
		var supported = this.getSupportedLanguages(null, true);
		
		// Parse the rules as an Accept-Language header if it is a string.
		if ($.isString(rules)) rules = this.parseAcceptLanguage(rules);
		
		// Go through the list of supported languages and determine what matches the language rules.
		for (var i = supported.length - 1; i >= 0; i--) {
			var matchedRule = this.determineRuleForLanguage(rules, supported[i]);
			if (matchedRule === false || matchedRule.quality == 0) {
				supported.splice(i, 1);
			}
			else {
				supported[i] = $.extend({ lang: supported[i] }, matchedRule);
			}
			
		}
		
		this.sortLanguageRules(supported);
		
		return supported;
	},
	
	getTranslation: function(lang, key) {
		if (!this.isLanguageLoaded(lang) || !this._languages[lang][key]) return null;
		return this._languages[lang][key];
	},
	
	hasTranslation: function(lang, key) {
		return this.getTranslation(lang, key) != null;
	}
});

})(jQuery);