/*
 * Modified to work without GWT. Requires jQuery.
 * By André Mekkawi diskusage@andremekkawi.com
 * Version: $Source Version$
 *
 * Tested in IE 6/7, Firefox 4, Safari 5, Chrome 11
 */

/*
Note: The following HTML must be included within the <body> tags in your page:
<iframe src="javascript:''" id="__gwt_historyFrame" style="width:0; height:0; border:0; position: absolute; left: 0; top: 0;"></iframe>

Example usage:

$(function(){
	// Init the history helper.
	$.history = new History();
	
	// Listen for hash changes.
	$.history.addHandler(function(token){
		// Process token ...
	});
	
	// Handle <a> tags.
	$('a[href]').live(function(e){
		e.preventDefault();
		$.history.newItem($(this.attr('href'));
		
		// Process token ...
	});
	
	var token = $.history.getToken();
	// Process initial token ...
});

*/

/*
 * Copyright 2008 Google Inc.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 */

;(function($){

var History = window.History = function() {
	this.init();
};

History.prototype = {
	token: '',
	handlers: [],
	mode: 'onhashchange',
	
	getToken: function() {
		return this.token;
	},
	
	setToken: function(token) {
		this.token = token;
	},
	
	addHandler: function(fn) {
		this.handlers.push(fn);
	},
	
	fireHistoryChangedImpl: function(newToken) {
		for (var key in this.handlers) {
			this.handlers[key](newToken);
		}
	},
	
	init: function() {
		var token = '', self = this;
		
		// Get the initial token from the url's hash component.
		var hash = window.location.hash;
		if (hash.length > 0) {
			token = this.decodeFragment(hash.substring(1));
		}
		
		this.setToken(token);
		
		var oldHandler = window.onhashchange;
		
		window.onhashchange = function(){
			var token = '', hash = window.location.hash;
			if (hash.length > 0) {
				token = self.decodeFragment(hash.substring(1));
			}
			
			self.newItemOnEvent(token);
			
			if (oldHandler) {
				oldHandler();
			}
		};
		
		return true;
	},
	
	newItem: function(historyToken, issueEvent) {
		historyToken = (historyToken == null) ? "" : historyToken;
		if (historyToken != this.getToken()) {
			this.setToken(historyToken);
			this.nativeUpdate(historyToken);
			if (issueEvent) {
				this.fireHistoryChangedImpl(historyToken);
			}
		}
	},
	
	newItemOnEvent: function(historyToken) {
		historyToken = (historyToken == null) ? "" : historyToken;
		if (historyToken != this.getToken()) {
			this.setToken(historyToken);
			this.nativeUpdateOnEvent(historyToken);
			this.fireHistoryChangedImpl(historyToken);
		}
	},
	
	decodeFragment: function(encodedFragment) {
		// decodeURI() does *not* decode the '#' character.
		return decodeURI(encodedFragment.replace("%23", "#"));
	},
	
	encodeFragment: function(fragment) {
		// encodeURI() does *not* encode the '#' character.
		return encodeURI(fragment).replace("#", "%23");
	},
	
	/**
	* The standard updateHash implementation assigns to location.hash() with an
	* encoded history token.
	*/
	nativeUpdate: function(historyToken) {
		window.location.hash = this.encodeFragment(historyToken);
	},
	
	nativeUpdateOnEvent: function(historyToken) {
		// Do nothing, the hash is already updated.
	}
};

/**
 * Base class for history implementations that use a timer rather than the
 * onhashchange event.
 */
var Timer = {
	mode: 'timer',
	init: function() {
		var token = '';
		
		// Get the initial token from the url's hash component.
		var hash = window.location.hash;
		if (hash.length > 0) {
			token = this.decodeFragment(hash.substring(1));
		}
		
		this.setToken(token);
		
		// Create the timer that checks the browser's url hash every 1/4 s.
		var self = this;
		
		var checkHistory = function() {
			var token = '', hash = window.location.hash;
			if (hash.length > 0) {
				token = self.decodeFragment(hash.substring(1));
			}
			
			self.newItemOnEvent(token);
		};
		
		var checkHistoryCycle = function() {
			window.setTimeout(checkHistoryCycle, 250);
			checkHistory();
		};
		
		// Kick off the timer.
		checkHistoryCycle();
		return true;
	}
};

/*
 * >> DISABLED since this causes the title in history menus to instead show the URL <<
 * 
 * Use the HTML5 popstate/pushState if the browser supports it.
 */
if ('pushStateDISABLED' in window.history) {
	$.extend(History.prototype, {
		mode: 'html5',
		init: function() {
			var token = '', self = this;
			
			// Get the initial token from the url's hash component.
			var hash = window.location.hash;
			if (hash.length > 0) {
				token = this.decodeFragment(hash.substring(1));
			}
			
			this.setToken(token);
			
			var oldHandler = window.popstate;
			
			$(window).bind('popstate', function(){
				var token = '', hash = window.location.hash;
				if (hash.length > 0) {
					token = self.decodeFragment(hash.substring(1));
				}
				
				self.newItemOnEvent(token);
				
				if (oldHandler) {
					oldHandler.apply(this, arguments);
				}
			});
			
			return true;
		},
		
		nativeUpdate: function(token) {
			window.history.pushState(null, null, window.location.pathname + window.location.search + '#' + this.encodeFragment(token));
		}
	});
}

else if ($.browser.opera) {
	$.extend(History.prototype, Timer, { mode: 'opera' });
}

/**
 * Safari implementation of
 * {@link com.google.gwt.user.client.impl.HistoryImplTimer}.
 * 
 * This implementation works on both Safari 2 and 3, by detecting the version
 * and reverting to a stub implementation for Safari 2.
 */
else if ($.browser.safari || $.browser.webkit) {
	$.extend(History.prototype, Timer, {
		mode: 'safari',
		nativeUpdate: function(historyToken) {
			// Safari gets into a weird state (issue 2905) when setting the hash
			// component of the url to an empty string, but works fine as long as you
			// at least add a '#' to the end of the url. So we get around this by
			// recreating the url, rather than just setting location.hash.
			window.location = window.location.href.split('#')[0] + '#'
				+ this.encodeFragment(historyToken);
		}
	});
}

else if ($.browser.msie && $.browser.version.match(/^[67]\./) != null) {
	$.extend(History.prototype, {
		mode: 'ie',
	
		/**
		 * Sanitizes an untrusted string to be used in an HTML context. NOTE: This
		 * method of escaping strings should only be used on Internet Explorer.
		 * 
		 * @param maybeHtml untrusted string that may contain html
		 * @return sanitized string
		 */
		escapeHtml: function(maybeHtml) {
			return $('<div>').text(maybeHtml).html();
		},
		
		findHistoryFrame: function() {
			return $('#__gwt_historyFrame').get(0);
		},
		
		/**
		* For IE6, reading from $wnd.location.hash drops part of the fragment if the
		* fragment contains a '?'. To avoid this bug, we use location.href instead.
		*/
		getLocationHash: function() {
			var href = window.location.href,
				hashLoc = href.lastIndexOf("#");
				
			return (hashLoc > 0) ? href.substring(hashLoc) : "";
		},
		
		getTokenElement: function(historyFrame) {
			// Initialize the history iframe.  If '__gwt_historyToken' already exists, then
			// we're probably backing into the app, so _don't_ set the iframe's location.
			if (historyFrame.contentWindow) {
				var doc = historyFrame.contentWindow.document;
				return doc.getElementById('__gwt_historyToken');
			}
		},
		
		historyFrame: null,
		reloadedWindow: false,
		
		// @Override
		init: function() {
			this.historyFrame = this.findHistoryFrame();
			if (!this.historyFrame) {
				return false;
			}
		
			this.initHistoryToken();
			
			// Initialize the history iframe. If a token element already exists, then
			// we're probably backing into the app, so _don't_ create a new item.
			var tokenElement = this.getTokenElement(this.historyFrame);
			if (tokenElement != null) {
				this.setToken(this.getTokenElementContent(tokenElement));
			} else {
				this.navigateFrame(this.getToken());
			}
			
			this.injectGlobalHandler();
			this.initUrlCheckTimer();
			return true;
		},
		
		// @Override
		nativeUpdate: function(historyToken) {
			/*
			* Must update the location hash since it isn't already correct.
			*/
			this.updateHash(historyToken);
			this.navigateFrame(historyToken);
		},
		
		// @Override
		nativeUpdateOnEvent: function(historyToken) {
			this.updateHash(historyToken);
		},
		
		getTokenElementContent: function(tokenElement) {
			return tokenElement.innerText;
		},
		
		/**
		* The URL check timer sometimes reloads the window to work around an IE bug.
		* However, the user might cancel the page navigation, resulting in a mismatch
		* between the current history token and the URL hash value.
		* 
		* @return true if a canceled window reload was handled
		*/
		handleWindowReloadCanceled: function() {
			if (this.reloadedWindow) {
				this.reloadedWindow = false;
				this.updateHash(this.getToken());
				return true;
			}
			return false;
		},
		
		initHistoryToken: function() {
			// Assume an empty token.
			var token = '';
			
			// Get the initial token from the url's hash component.
			var hash = this.getLocationHash();
			if (hash.length > 0) {
				try {
					token = this.decodeFragment(hash.substring(1));
				} catch (e) {
					// Clear the bad hash (this can't have been a valid token).
					window.location.hash = '';
				}
			}
			this.setToken(token);
		},
		
		initUrlCheckTimer: function() {
			// This is the URL check timer.  It detects when an unexpected change
			// occurs in the document's URL (e.g. when the user enters one manually
			// or selects a 'favorite', but only the #hash part changes).  When this
			// occurs, we _must_ reload the page.  This is because IE has a really
			// nasty bug that totally mangles its history stack and causes the location
			// bar in the UI to stop working under these circumstances.
			var self = this;
			var urlChecker = function() {
				window.setTimeout(urlChecker, 250);
			
				// Reset the hash if the user cancels a window reload triggered by the 
				// urlChecker.
				if (self.handleWindowReloadCanceled()) {
					return;
				}
				
				var hash = self.getLocationHash();
				if (hash.length > 0) {
					var token = '';
					try {
						token = self.decodeFragment(hash.substring(1));
					} catch (e) {
						// If there's a bad hash, always reload. This could only happen if
						// if someone entered or linked to a bad url.
						self.reloadWindow();
					}
					
					var historyToken = self.getToken();
					if (historyToken && (token != historyToken)) {
						self.reloadWindow();
					}
				}
			};
			urlChecker();
		},
		
		injectGlobalHandler: function() {
			var self = this;
			var oldOnLoad = window.__gwt_onHistoryLoad;
			
			window.__gwt_onHistoryLoad = function(token) {
				self.newItemOnEvent(token);
				
				if (oldOnLoad) {
					oldOnLoad(token);
				}
			};
		},
		
		navigateFrame: function(token) {
			var escaped = this.escapeHtml(token);
			var doc = this.historyFrame.contentWindow.document;
			doc.open();
			doc.write('<html><body onload="if(parent.__gwt_onHistoryLoad)parent.__gwt_onHistoryLoad(__gwt_historyToken.innerText)"><div id="__gwt_historyToken">' + escaped + '</div></body></html>');
			doc.close();
		},
		
		reloadWindow: function() {
			reloadedWindow = true;
			window.location.reload();
		},
		
		updateHash: function(token) {
			window.location.hash = this.encodeFragment(token);
		}
	
	});
}

/**
 * History tweaks for older Mozilla-based browsers.
 */
else if ($.browser.mozilla && $.browser.version.indexOf('1.8') == 0) {
	$.extend(History.prototype, Timer, {
		mode: 'mozilla',
	
		decodeFragment: function(encodedFragment) {
			// Mozilla browsers pre-decode the result of location.hash, so there's no
			// need to decode it again (which would in fact be incorrect).
			return encodedFragment;
		},

		/**
		 * When the historyToken is blank or null, we are not able to set
		 * $wnd.location.hash to the empty string, due to a bug in Mozilla. Every time
		 * $wnd.location.hash is set to the empty string, one of the characters at the
		 * end of the URL stored in $wnd.location is 'eaten'. To get around this bug,
		 * we generate the module's URL, and we append a '#' character onto the end.
		 * Without the '#' character at the end of the URL, Mozilla would reload the
		 * page from the server.
		*/
		nativeUpdate: function(historyToken) {
			if (historyToken.length == 0) {
				var s = window.location.href;
				// Pull off any hash.
				var i = s.indexOf('#');
				if (i != -1)
					s = s.substring(0, i);
				
				window.location = s + '#';
			} else {
				window.location.hash = this.encodeFragment(historyToken);
			}
		}
	});
}

/*
 * Fall back to timer if onhashchange is not supported
 */
else if (!('onhashchange' in window)) {
	$.extend(History.prototype, Timer);
}

})(jQuery);