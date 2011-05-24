// TODO: Give credit to original authors.

String.prototype.htmlencode = function() {
	var div = document.createElement("div");
	div.appendChild(document.createTextNode(this));
	return div.innerHTML.replace(/"/g, "&quot;");
};

RegExp.escape = function(str) {
	var specials = new RegExp("[.*+?|()\\[\\]{}\\\\/]", "g"); // .*+?|()[]{}\/
	return str.replace(specials, "\\$&");
};

String.prototype.parseQS = function() {
	var result = {};
	var str = this.indexOf('?') == 0 ? this.substring(1) : this;
	var pairs = str.split('&');
	for (var i = 0; i < pairs.length; i++) {
		var pair = pairs[i].split('=');
		var key = unescape(pair[0]);
		var value = typeof pair[1] == "undefined" ? null : unescape(pair[1]);
		
		// Key has not yet been set.
		if ($.isUndefined(result[key])) {
			result[key] = value;
		}
		
		// Key has been set and is an array.
		else if ($.isArray(result[key])) {
			result[key][result[key].length] = value;
		}
		
		// Key has been set and is not yet an array.
		else {
			result[key] = [result[key], value];
		}
	}
	return result;
};

function FormatBytes(bytes, forceBytes) {
	var byteText = AddCommas(bytes+'') + ' bytes';
	if (bytes >= 1000 * 1024 * 1024) {
		return AddCommas(Math.round(bytes * 100 / 1024 / 1024 / 1024) / 100) + ' GB' + (forceBytes ? ' (' + byteText + ')' : '');
	}
	else if (bytes >= 1000 * 1024) {
		return AddCommas(Math.round(bytes * 100 / 1024 / 1024) / 100) + ' MB' + (forceBytes ? ' (' + byteText + ')' : '');
	}
	else if (bytes >= 1000) {
		return AddCommas(Math.round(bytes * 100 / 1024) / 100) + ' KB' + (forceBytes ? ' (' + byteText + ')' : '');
	}
	else {
		return byteText;
	}
}

function AddCommas(nStr) {
	nStr += '';
	x = nStr.split('.');
	x1 = x[0];
	x2 = x.length > 1 ? '.' + x[1] : '';
	var rgx = /(\d+)(\d{3})/;
	while (rgx.test(x1)) {
		x1 = x1.replace(rgx, '$1' + ',' + '$2');
	}
	return x1 + x2;
}

function BinarySearch(haystack, needle, comparator) {
	var low = 0, high = haystack.length - 1, comp = -1, mid = 0;

	if (typeof comparator != 'function') {
		comparator = function(needle, item, index) {
			if (needle < item) return -1;
			if (needle > item) return 1;
			else return 0;
		};
	}
	
	while (low <= high) {
		var mid = Math.floor((low + high) / 2);
		var comp = comparator(needle, haystack[mid], mid);
		
		if (comp < 0) {
			high = mid - 1;
		}
		else if (comp > 0) {
			low = mid + 1;
		}
		else {
			return mid;
		}
	}

	if (comp < 0) return -1 - mid;
	if (comp > 0) return -2 - mid;
}

jQuery.fn.extend({
	equals: function(elem) {
		if (!elem || this.size() == 0 || ($.isJQuery(elem) && elem.size() == 0)) return false;
		if ($.isJQuery(elem)) elem = elem.get(0);
		
		return !(this.get(0) !== elem);
	},
	isParentOf: function(elem) {
		return $(elem).isChildOf(this);
	},
	isChildOf: function(elem) {
		if (!elem || this.size() == 0 || ($.isJQuery(elem) && elem.size() == 0)) return false;
		if ($.isJQuery(elem)) elem = elem.get(0);
		var comp = this.get(0);
		
		while (comp = comp.parentNode) {
			if (!(elem !== comp)) return true;
		}
		
		return false;
	},
	disableTextSelection: function() {
		return this
			.attr('unselectable', 'on')
			.css('MozUserSelect', 'none')
			.bind('selectstart.ui', function() { return false; });
	},
	enableTextSelection: function() {
		return this
			.attr('unselectable', 'off')
			.css('MozUserSelect', '')
			.unbind('selectstart.ui');
	}
});

jQuery.extend({
	isJQuery: function(obj) {
		return obj instanceof jQuery;
	},
	isBoolean: function(obj) {
	    return typeof obj == 'boolean';
	},
	
	isNull: function(obj) {
	    return obj === null;
	},
	
	isNumber: function(obj) {
	    return typeof obj == 'number' && isFinite(obj);
	},
	
	isObject: function(obj) {
	    return (obj && typeof obj == 'object') || $.isFunction(obj);
	},
	
	isString: function(obj) {
	    return typeof obj == 'string';
	},
	
	isUndefined: function(obj) {
	    return typeof obj == 'undefined';
	},
	
	isDefined: function(obj) {
	    return typeof obj != 'undefined';
	},
	
	isElement: function(object) {
    	return $.isObject(object) && $.isDefined(object.nodeType) && object.nodeType == 1;
  	},
	
	isDocumentNode: function(object) {
		if ($.isJQuery(object) && object.size() > 0) object = object.get(0);
		return $.isObject(object) && $.isDefined(object.nodeType) && object.nodeType == 9;
	},
	
	explode: function(str, delim, limit) {
		var arr = [];
		var index = -1;
		while ((index = str.indexOf(delim)) >= 0 && (!limit || arr.length < limit - 1)) {
			arr[arr.length] = str.substring(0, index);
			if (str.substring(index) == delim) arr[arr.length] = '';
			str = str.substring(index + 1);
		}
		if (str.length > 0) arr[arr.length] = str;
		return arr;
	},
	
	implode: function(arr, delim, lastdelim) {
		var str = "";
		if ($.isArray(arr)) {
			if (arr.length == 1) return arr[0] + '';
			for (var i = 0; i < arr.length; i++) {
				if (lastdelim && i == arr.length - 1) {
					str += (lastdelim + '');
				}
				else if (i > 0) {
					str += (delim + '');
				}
				
				str += (arr[i] + '');
			}
		}
		return str;
	},
	
	dumpWindow: function(obj) {
		var outer = $('<div></div>')
			.data('dump', obj)
			.css({
				'z-index': '9999999999',
				'font-family': 'Verdana',
				'font-size': '11px',
				'min-width': '200px',
				position: 'absolute',
				top: '20px',
				left: '20px',
				border: '2px solid #666',
				'background-color': '#FFF',
				padding: '2px' });
		var close = $('<div>Close</div>')
			.css({
				'text-align': 'right',
				'margin-bottom': '1px',
				'background-color': '#EEE',
				padding: '5px',
				'float': 'right',
				cursor: 'pointer' })
			.appendTo(outer).click(function(){
				$(this).parent().remove();
			});
		
		var expand = function(obj, container) {
			var valueList = [];
			var arrayList = [];
			var undefinedList = [];
			var functionsList = [];
			var objectsList = [];
			var unreadableList = [];
			
			// Go through all the properties of the passed-in object
			for (var i in obj) {
				try {
					if ($.isUndefined(obj[i])) {
						undefinedList[undefinedList.length] = i;
					}
					else if ($.isArray(obj[i])) {
						arrayList[arrayList.length] = i;
					}
					else if ($.isFunction(obj[i])) {
						functionsList[functionsList.length] = i;
					}
					else if ($.isObject(obj[i])) {
						objectsList[objectsList.length] = i;
					}
					else {
						valueList[valueList.length] = i;
					}
				}
				catch (err) {
					unreadableList[unreadableList.length] = i;
				}
			}
			
			valueList.sort();
			arrayList.sort();
			undefinedList.sort();
			functionsList.sort();
			objectsList.sort();
			unreadableList.sort();
			
			var header = function(text) {
				return $('<div></div>')
					.css({ padding: '8px 0 0 8px', 'font-size': '20px', 'font-weight': 'bold' })
					.text(text)
					.toggle(function() {
						$(this).css('font-style', 'italic').next().hide();
					}, function() {
						$(this).css('font-style', '').next().show();
					});
			};
			
			// Values
			if (valueList.length > 0) {
				header('Values').appendTo(container);
				var valueTable = $('<table border="1" cellpadding="4" cellspacing="0" style="clear: both"></table>').appendTo(container);
				for (var i = 0; i < valueList.length; i++) {
					$('<tr></tr>')
						.append($('<td></td>').text(valueList[i]))
						.append($('<td></td>').text(obj[valueList[i]] + ''))
						.appendTo(valueTable);
				}
			}
			
			// Objects
			if (objectsList.length > 0) {
				header('Objects').appendTo(container);
				var objectTable = $('<table border="1" cellpadding="4" cellspacing="0" style="clear: both"></table>').appendTo(container);
				for (var i = 0; i < objectsList.length; i++) {
					$('<tr></tr>')
						.append($('<td></td>').css('vertical-align', 'top').text(objectsList[i]))
						.append(
							$('<td></td>')
							.append(
								$('<div></div>')
									.text(obj[objectsList[i]] + '')
									.css('cursor', 'pointer')
									.one('click', { obj: obj[objectsList[i]] },
										function(e) {
											$(this).toggle(
												function() { $(this).siblings().hide(); },
												function() { $(this).siblings().show(); }
											);
											expand(e.data.obj, $(this).parent());
										}
									)
							)
						)
						.appendTo(objectTable);
				}
			}
			
			// Arrays
			if (arrayList.length > 0) {
				header('Arrays').appendTo(container);
				var tmp = '';
				arrayList.sort();
				for (var i = 0; i < arrayList.length; i++) {
					tmp += (i > 0 ? ', ': '') + arrayList[i];
				}
				$('<div></div>').text(tmp).appendTo(container);
			}
			
			// Functions
			if (functionsList.length) {
				header('Functions').appendTo(container);
				var tmp = '';
				functionsList.sort();
				for (var i = 0; i < functionsList.length; i++) {
					tmp += (i > 0 ? ', ': '') + functionsList[i];
				}
				$('<div></div>').css('max-width', '600px').text(tmp).appendTo(container);
			}
			
			// Undefined
			if (undefinedList.length > 0) {
				header('Undefined').appendTo(container);
				var tmp = '';
				undefinedList.sort();
				for (var i = 0; i < undefinedList.length; i++) {
					tmp += (i > 0 ? ', ': '') + undefinedList[i];
				}
				$('<div></div>').text(tmp).appendTo(container);
			}
			
			// Unreadable
			if (unreadableList.length) {
				header('Unreadable').appendTo(container);
				var tmp = '';
				unreadableList.sort();
				for (var i = 0; i < unreadableList.length; i++) {
					tmp += (i > 0 ? ', ': '') + unreadableList[i];
				}
				$('<div></div>').css('max-width', '600px').text(tmp).appendTo(container);
			}
		};

		
		if ($.isObject(obj))
			expand(obj, outer);
		else
			$('<div></div>').text(obj).appendTo(outer);
		
		$('body').append(outer);
		
		//return msg;
	}
});