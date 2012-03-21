// Version: $Source Version$

// TODO: Give credit to original authors as necessary.

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
			.css({ 'MozUserSelect': 'none', 'WebkitUserSelect': 'none' })
			.bind('selectstart.ui', function() { return false; });
	},
	enableTextSelection: function() {
		return this
			.attr('unselectable', 'off')
			.css({'MozUserSelect': '', 'WebkitUserSelect': ''})
			.unbind('selectstart.ui');
	}
});

jQuery.extend({
	getArrayKeys: function(arr) {
		var keys = new Array();
		if ($.isObject(arr) || $.isArray(arr)) {
			for (var key in arr) {
				keys.push(key);
			}
		}
		return keys;
	},
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
	}
});