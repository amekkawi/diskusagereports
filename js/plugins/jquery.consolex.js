/* 
 * Copyright (c) 2011 André Mekkawi <diskusage@andremekkawi.com>
 * Version: $Source Version$
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

$.fn.log = function(o, sub) {
	var compactListCSS = { 'list-style': 'none', 'margin': '0', 'padding': '0', 'display': 'inline' },
		expandedListCSS = { 'list-style': '', 'margin': '', 'padding': '', 'display': '' },
		charLimit = 200,
		container = $(this).eq(0),
		passedFirst = false,
		passedLimit = false,
		ul, more, populate;
	
	var toggleList = function() {
		if (populate) {
			ul.empty();
			populate();
		}
		
		else if (ul.css('display') == 'inline') {
			ul.css(expandedListCSS)
				.find('>li').css(expandedListCSS).end()
				.find('>span').hide().end();
			if (more) more.hide();
		}
		else {
			ul.css(compactListCSS)
				.find('>li').css(compactListCSS).end()
				.find('>span').css('display', 'inline').end()
				.find('>.hide-on-collapse').hide().end();
			if (more) more.show();
		}
	};
	
	switch ($.type(o)) {
		
		case 'string':
			$('<span>').css({ 'color': '#F00' }).text('"' + o + '"').appendTo(container);
			break;
		
		case 'array':
			$('<span>').text('Array [ ').css('cursor', 'pointer').click(toggleList).appendTo(container);
			ul = $('<ul>').css(compactListCSS).appendTo(container);
			
			populate = function(){
				for (var i = 0; i < o.length; i++) {
					if (passedFirst) $('<span>').text(', ').appendTo(ul);
					var li = $('<li>').css(compactListCSS).appendTo(ul);
					$('<span>').css({ 'color': '#060' }).text(i + ': ').appendTo(li);
					li.log(o[i], true);
					passedFirst = true;
				}
				
				for (var key in o) {
					if (isNaN(key)) {
						if (passedFirst) $('<span>').text(', ').appendTo(ul);
						var li = $('<li>').css(compactListCSS).appendTo(ul);
						$('<span>').css({ 'color': '#060' }).text(key + ': ').appendTo(li);
						li.log(o[i], true);
						passedFirst = true;
					}
				}
				
				populate = null;
			};
			
			if (sub) {
				$('<li>').text('...').css(compactListCSS).css({ cursor: 'pointer', color: '#00F' }).appendTo(ul).click(function(){
					ul.empty();
					populate();
					toggleList();
				});
			}
			else {
				populate();
			}
			
			container.append(document.createTextNode(' ]'));
			break;
		
		case 'object':
			$('<span>').css({ 'color': '#060' }).text('Object { ').css('cursor', 'pointer').click(toggleList).appendTo(container);
			ul = $('<ul>').css(compactListCSS).appendTo(container);
			
			populate = function() {
				for (var key in o) {
					if (passedFirst) $('<span>').text(', ').addClass(passedLimit ? 'hide-on-collapse' : '').css('display', passedLimit ? 'none' : 'show').appendTo(ul);
					
					var li = $('<li>').addClass(passedLimit ? 'hide-on-collapse' : '').css(compactListCSS)[passedLimit ? 'hide' : 'show']().appendTo(ul);
					$('<span>').css({ 'color': '#060' }).text(key + ': ').appendTo(li);
					try { li.log(o[key], true); }
					catch(e) { $('<span>').css({ 'color': '#F00', 'text-decoration': 'blink' }).text('Exception ').appendTo(li); li.log(e); }
					passedFirst = true;
					passedLimit = passedLimit ? passedLimit : ul.text().length > charLimit;
				}
				
				if (passedLimit) {
					more = $('<span>').text(', ...').css({ cursor: 'pointer', color: '#00F' }).click(function(){
						toggleList();
					}).appendTo(ul);
				}
				
				populate = null;
			};
			
			if (sub) {
				$('<li>').text('...').css(compactListCSS).css({ cursor: 'pointer', color: '#00F' }).appendTo(ul).click(toggleList);
			}
			else {
				populate();
			}
			
			$('<span>').css({ 'color': '#060' }).text(' }').appendTo(container);
			break;
		
		case 'function':
			$('<span>')/*.css({ 'color': '#00C' })*/.text('function()').appendTo(container);
			break;
		
		case 'undefined':
			$('<span>').css({ 'color': '#666', 'font-style': 'italic' }).text('undefined').appendTo(container);
			break;
		
		case 'boolean':
			$('<span>').css({ 'color': '#06F' }).text(o ? 'true' : 'false').appendTo(container);
			break;
		
		case 'number':
			$('<span>').css({ 'color': '#06F' }).text(o+'').appendTo(container);
			break;
		
		case 'date':
		case 'regexp':
		
		default:
			container.append(document.createTextNode($.type(o)));
			break;
	}
	
	return this;
};

window.consolex = {
	hasInit: false,
	init: function() {
		if (!window.consolex.hasInit) {
			window.consolex.hasInit = true;
			
			var container = window.consolex.container = $('<div>')
				.css({
					'z-index': '999999999',
					position: 'absolute',
					top: '6px',
					right: '6px',
					'background-color': '#EEE',
					height: '200px',
					width: '75%',
					border: '1px solid #666'
				})
				.appendTo('body');
			
			var inner = $('<div>').css({
					margin: '6px'
				}).appendTo(container);
			
			var scroller = $('<div>')
				.css({
					overflow: 'auto',
					height: '188px',
					width: '100%'
				}).appendTo(inner);
			
			var toggle = $('<div>')
				.text('Toggle')
				.css({
					position: 'absolute',
					right: '4px',
					top: '4px',
					cursor: 'pointer',
					'background-color': '#FFF',
					padding: '3px',
					border: '1px solid #666'
				}).click(function(){
					
					if (inner.is(':visible')) {
						container.css({ width: '57px', height: '32px' });
						inner.hide();
					}
					else {
						container.css({ width: '75%', height: '200px' });
						inner.show();
					}
					
				}).appendTo(container);
			
			window.consolex.ul = $('<ul>').appendTo(scroller);
		}
	},
	
	log: function(a){
		window.consolex.init();
		$('<li>').log(a).appendTo(window.consolex.ul);
		window.consolex.container.scrollTop(99999);
	}
};

if (!('console' in window)) {
	window.console = window.consolex;
}