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
	
	$('#ErrorCount')
		.data('count', 0)
		.click(function() {
			$('#ErrorsDialog').dialog('open');
		})
		.disableTextSelection();
	
	$('#ErrorsDialog').dialog({
		backgroundColor: '#FFF',
		borderColor: '#CC0000',
		opening: function(e, contents) {
			self.populateErrors(contents);
		}
	});
});

$.extend(Controller.prototype, {
	_errors: [],
	
	reportError: function(message, detail) {
		this.reportErrors([ [ message, detail ] ]);
	},
	
	reportErrors: function(errors) {
		this._errors.push.apply(this._errors, errors);
		$('#ErrorCount').show().find('span').text(this._errors.length);
	},
	
	populateErrors: function(contents) {
		contents.empty();
		for (var i = 0; i < this._errors.length; i++) {
			var errorItem = $('<div>')
				.addClass('errors-item')
				.html( $('<b>').html($.isArray(this._errors[i][0]) ? this.translate.apply(this, this._errors[i][0]) : this._errors[i][0]) )
				.appendTo(contents);
			
			if (this._errors[i][1]) errorItem.append(this._errors[i][1]);
		}
	}
});

})(jQuery);