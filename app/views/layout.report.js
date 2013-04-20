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
	'i18n!nls/report',
	'models/model.report',
	'models/model.settings',
	'views/view.title',
	'views/layout.report-body',
	'views/view.footer'
], function(Backbone, Layout, _, lang, ModelReport, ModelSettings, TitleView, ReportBodyView, FooterView){

	return Layout.extend({

		tagName: 'div',
		className: 'du-report du-loading',

		models: null,

		initialize: function() {
			_.defaults(this.options, {
				suffix: [ '.txt', '' ],

				gradient: [ '007eff','0081f8','0084ef','0088e4','008dd8','0092cb','0097bd','009cae',
					'00a29f','00a88f','00ae80','00b275','00b66a','00ba60','00be56','00c24c',
					'00c642','00ca38','00cd2f','00d127','00d41f','00d717','00da10','03dd0a',
					'07e005','0be200','15e600','22eb00','31ef00','42f300','55f600','69fa00',
					'7dfd00','8bfe00','93ff00','9cff00','a5ff00','adff00','b5ff00','bdff00',
					'c5ff00','cdff00','d4ff00','dbff00','e2ff00','e8ff00','eeff00','f3ff00',
					'f8ff00','fcff00','ffff00','fffd00','fffa00','fff800','fff500','fff200',
					'ffee00','ffea00','ffe600','ffe200','ffde00','ffd900','ffd400','ffcf00',
					'ffca00','ffc500','ffc000','ffba00','ffb500','ffb000','ffaa00','ffa500',
					'ffa000','ff9a00','ff9500','ff9000','ff8d00','ff8900','ff8600','ff8200',
					'ff7f00','ff7b00','ff7700','ff7300','ff6f00','ff6b00','ff6700','ff6300',
					'ff5f00','ff5a00','ff5600','ff5200','ff4e00','ff4900','ff4500','ff4100',
					'ff3100','ff2200','ff1400','ff0900' ],

				pageMax: 100
			});

			var models = this.models = {
				report: this.model = new ModelReport({}, {
					suffix: this.options.suffix,
					urlRoot: this.options.urlRoot
				}),
				settings: new ModelSettings({}, {
					suffix: this.options.suffix,
					urlRoot: this.options.urlRoot
				})
			};

			this.setViews({
				'': [
					this._titleView = new TitleView({ model: models.settings }),
					new ReportBodyView({ models: models }),
					this._footerView = new FooterView({ model: models.settings })
				]
			});
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
				view.resize && view.resize(maxWidth, innerHeight);
			});
		},

		addListeners: function() {
			// Report setup only done once after valid settings are loaded.
			this.models.settings.once("change", function(model){
				if (model.isValid()) {
					if (!model.attributes.directorytree)
						this.$el.addClass('du-notree');

					this.$el.removeClass('du-loading');
					this.resize();
				}
			}, this);

			this.getViews().each(function(view){
				view.addListeners();
			});
			return this;
		},

		load: function(options) {
			var models = this.models,
				loadOptions = options || {};

			// Delay the loading message to avoid it from blinking quickly.
			var messageDelay = _.delay(function(){
				models.report.set({
					message: lang['message_loading'],
					messageType: 'loading'
				});
			}, 250);

			// Start with the settings file.
			models.settings.fetch({
				success: function(model, response, options) {
					clearTimeout(messageDelay);

					if (!model.isValid()) {
						models.report.set({
							message: lang['message_settings_invalid'],
							messageType: 'error'
						});
					}
					else {
						models.report.set('message', null);

						if (model.attributes.directorytree) {
							// TODO: Start loading the directory tree.
						}

						loadOptions.success && loadOptions.success.call(this, models);
					}
				},
				error: function(model, response, options){
					clearTimeout(messageDelay);
					models.report.set({
						message: _.template(lang['message_settings_' + (response.status || response.statusText)] || lang['message_settings'], { status: (response.status || response.statusText)+'' }),
						messageType: 'error'
					});
					loadOptions.error && loadOptions.error.apply(this, arguments);
				}
			});
		}
	});

});