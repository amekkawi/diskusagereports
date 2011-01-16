/**
 * @author amekkawi
 */
String.prototype.htmlencode = function() {
	var div = document.createElement("div");
	div.appendChild(document.createTextNode(this));
	return div.innerHTML.replace(/"/g, "&quot;");
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

function ArrayFlip(arr) {
    var key, flipped = { };
    for (key in arr) flipped[arr[key]] = key;
    return flipped;
}

function FormatBytes(bytes, forceBytes) {
	var byteText = AddCommas(bytes+'') + ' bytes';
	if (bytes >= 1024 * 1024 * 1024) {
		return AddCommas(Math.round(bytes * 100 / 1024 / 1024 / 1024) / 100) + ' GB' + (forceBytes ? ' (' + byteText + ')' : '');
	}
	else if (bytes >= 1024 * 1024) {
		return AddCommas(Math.round(bytes * 100 / 1024 / 1024) / 100) + ' MB' + (forceBytes ? ' (' + byteText + ')' : '');
	}
	else if (bytes >= 1024) {
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

function ArrayFlip(arr) {
	var key, result = {};
	for (key in arr) result[arr[key]] = key;
	return result;
};

function GetCenteredRectangle(cWidth, cHeight, iWidth, iHeight) {
	var left = 0;
	var top = 0;
	var width = iWidth;
	var height = iHeight;
	
	if (cWidth < iWidth) {
		width = cWidth;
	}
	else if (cWidth - iWidth > 0) {
		left = Math.floor((cWidth - iWidth) / 2);
	}
	
	if (cHeight < iHeight) {
		height = cHeight;
	}
	else if (cHeight - iHeight > 0) {
		top = Math.floor((cHeight - iHeight) / 2);
	}
	
	return { left: left, top: top, width: width, height: height };
}

function GetProportionalSize(cWidth, cHeight, iWidth, iHeight, expand) {
	var scaledHeight = Math.max(Math.min(cHeight, 1), Math.floor((iHeight * cWidth) / iWidth));
	var scaledWidth = Math.max(Math.min(cWidth, 1), Math.floor((iWidth * cHeight) / iHeight));
	
	if ($.isUndefined(expand)) expand = false;
	
	if (!expand && iWidth <= cWidth && iHeight <= cHeight) {
		return { width: iWidth, height: iHeight };
	}
	else if (scaledHeight <= cHeight) {
		return { width: cWidth, height: scaledHeight };
	}
	else {
		return { width: scaledWidth, height: cHeight };
	}
}

jQuery.fn.extend({
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
		//$.dump($this.parent().parent(), true);
	},
	dimensions: function() {
		return $.dimensions(this);
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
	
	uniqueArray: function(values, sorted) {
		var lookup = new Array();
		var result = new Array();
		
		for (var key in values) {
			if (!jQuery.isObject(values[key]) && typeof lookup[values[key]] == 'undefined') {
				lookup[values[key]] = true;
				if (!sorted) result[result.length] = values[key];
			}
		}
		
		if (sorted) {
			var keys = new Array();
			for (var key in lookup) {
				if (jQuery.isBoolean(lookup[key])) {
					keys[keys.length] = key;
				}
			}
			return keys;
		}
		else {
			return result;
		}
	},
	
	isValidDate: function(y_or_str, m, d) {
		// Handle the first argument being a string.
		if ($.isString(y_or_str) && y_or_str.match(/^[0-9]{4}[-\/][0-9]{2}[-\/][0-9]{2}$/)) {
			m = parseInt(y_or_str.substring(5, 7));
			d = parseInt(y_or_str.substring(8, 10));
			y_or_str = parseInt(y_or_str.substring(0, 4));
		}
		
		if ($.isNumber(y_or_str) && $.isNumber(m) && $.isNumber(d)) {
			var date = new Date(new Date(y_or_str, m - 1, d).getTime());
			return (y_or_str + '/' + m + '/' + d) === (date.getFullYear() + '/' + (date.getMonth()+1) + '/' + date.getDate());
		}
		else {
			return false;
		}
	},
	
	validateDate: function(y, m ,d) {
		var returnformat = 'full';
		if (!m) { d = m = 1; returnformat = 'year'; }
		else if (!d) { d = 1; returnformat = 'month'; }
		
		// Make sure all passed values are strings and are cleaned up.
		y = (y + '').replace(/^0+/, '');
		m = (m + '').replace(/^0+/, '');
		d = (d + '').replace(/^0+/, '');
		
		// Create a date string an validate it.
		var str = y + "/" + m + "/" + d;
		if (!str.match(/^\d{4}\/\d{1,2}\/\d{1,2}$/)) return false;
		
		// Change the passed values to numbers.
		y = parseInt(y); m = parseInt(m); d = parseInt(d);
		
		// Parse the string and return false if it is NaN.
		var p = Date.parse(str);
		if (isNaN(p)) return false;
		
		// Create a new date and verify that the parts match the passed arguments.
		var dt = new Date(p);
		if (dt.getFullYear() == y && dt.getMonth() + 1 == m && dt.getDate() == d) {
			
			// Return the validated parts of the date.
			if (returnformat == 'year') {
				return { year: y };
			}
			if (returnformat == 'month') {
				return { year: y, month: m };
			}
			else {
				return { year: y, month: m, day: d };
			}
		}
		else {
			return false;
		}
	},
	
	dump: function(obj, floatingWindow) {
		var msg = "";
		
		var undefinedList = "";
		var functionsList = "";
		var objectsList = "";
		
		// Go through all the properties of the passed-in object
		for (var i in obj) {
			if ($.isUndefined(obj[i])) {
				if (undefinedList != '') undefinedList += ', ';
				undefinedList += i;
			}
			else if ($.isFunction(obj[i])) {
				if (functionsList != '') functionsList += ', ';
				functionsList += i;
			}
			else if ($.isObject(obj[i])) {
				if (objectsList != '') objectsList += ', ';
				objectsList += i;
			}
			else {
				msg += i + ": " + obj[i] + "\n";
			}
		}
		msg += "\nFunctions: " + functionsList;
		msg += "\n\nObjects: " + objectsList;
		msg += "\n\nUndefined: " + undefinedList;
		
		if (floatingWindow) {
			var outer = $('<div></div>')
				.css({
					position: 'absolute',
					top: '20px',
					left: '20px',
					border: '1px solid #F00',
					'background-color': '#FFF',
					padding: '2px' });
			var close = $('<div>Close</div>')
				.css({
					'margin-bottom': '1px',
					'background-color': '#EEE',
					padding: '5px',
					cursor: 'pointer' })
				.appendTo(outer).click(function(){
					$(this).parent().remove();
				});
			var textarea = $('<textarea readonly="readonly"></textarea>')
				.css({ width: '500px', height: '300px' })
				.val(msg)
				.appendTo(outer);
			$('body').append(outer);
		}
		
		return msg;
	},
	
	dumpWindow: function(obj) {
		var outer = $('<div></div>')
			.data('dump', obj)
			.css({
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
	},
	
	dimensions: function(top, left) {
		var win = $(window), result = { };
		
		if ($.isJQuery(top) || $.isElement(top)) {
			// Set the values from an element.
			var $elem = $(top);
			$.extend(result, $elem.offset());
			result.width = $elem.outerWidth();
			result.height = $elem.outerHeight();
		}
		else {
			// Set the initial values from a point.
			result.top = top;
			result.left = left;
			result.width = 0;
			result.height = 0;
		}
		
		result.right = result.left + result.width;
		result.bottom = result.top + result.height;
		result.center = { top: (result.top + result.height) / 2, left: (result.left + result.width) / 2 };
		
		// Viewport
		result.viewport = {
			above: result.top - win.scrollTop(),
			below: win.height() - (result.top - win.scrollTop()) - result.height,
			left: result.left - win.scrollLeft(),
			right: win.width() - (result.left - win.scrollLeft()) - result.width
		};
			
		// Round all values
		for (var key in result) {
			if ($.isNumber(result[key]))
				result[key] = Math.round(result[key]);
			else
				for (var subkey in result[key])
					result[key][subkey] = Math.round(result[key][subkey]);
		}
		
		return result;
	}
});

function Queue(fifo) {
	this.nextID = 0;
	this.arr = [];
	this.fifo = fifo == true;
}
$.extend(Queue.prototype, {
	queue: function(obj, fn) {
		new Mutex(new QueueCmd(obj, fn, this), "queue");
	},
	dequeue: function(fn) {
		new Mutex(new QueueCmd(null, fn, this), "dequeue");
	},
	clear: function(fn) {
		new Mutex(new QueueCmd(null, fn, this), "clear");
	},
	size: function(fn) {
		new Mutex(new QueueCmd(null, fn, this), "size");
	}
});

function QueueCmd(obj, fn, queue) {
	this.id = ++queue.nextID;
	this.q = queue;
	this.obj = obj;
	this.fn = fn;
}
$.extend(QueueCmd.prototype, {
	queue: function() {
		this.q.arr.push(this.obj);
		if ($.isFunction(this.fn)) this.fn();
	},
	dequeue: function() {
		var obj = this.q.fifo ? this.q.arr.pop() : this.q.arr.shift();
		if ($.isFunction(this.fn)) this.fn(obj);
	},
	clear: function() {
		this.q.arr = [];
		if ($.isFunction(this.fn)) this.fn();
	},
	size: function() {
		if ($.isFunction(this.fn)) this.fn(this.q.arr.length);
	}
});