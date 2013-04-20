/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'backbone',
	'layout',
	'underscore',
	'views/view.title',
	'views/layout.report-body',
	'views/view.footer'
], function(Backbone, Layout, _, TitleView, ReportBodyView, FooterView){

	return Layout.extend({

		tagName: 'div',
		className: 'du-report du-loading',

		_models: null,

		initialize: function(options) {
			var models = this._models = options && options.models || {};

			this.setViews({
				'': [
					this._titleView = new TitleView({ model: models.settings }),
					new ReportBodyView({ model: models.report }),
					this._footerView = new FooterView({ model: models.settings })
				]
			});

			this.on("resize", this.resize, this);
		},

		resize: function(maxWidth, maxHeight) {
			if (!this.$el.is(':visible'))
				return;

			maxWidth = maxWidth || this._lastMaxWidth || this.$el.width();
			maxHeight = maxHeight || this._lastMaxHeight || this.$el.height();

			this._lastMaxWidth = maxWidth;
			this._lastMaxHeight = maxHeight;

			var innerHeight = maxHeight - this._titleView.$el.outerHeight(true) - this._footerView.$el.outerHeight(true);
			this.getViews().each(function(view){
				view.resize(maxWidth, innerHeight);
			});
		},

		addListeners: function() {
			if (this._models.settings) {
				this._models.settings.once("change", function(model){
					if (model.isValid()) {
						this.$el.removeClass('du-loading');
						this.resize();
					}
				}, this);
			}

			this.getViews().each(function(view){
				view.addListeners();
			});
			return this;
		}
	});

});