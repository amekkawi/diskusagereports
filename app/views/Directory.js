"use strict";
define([
	'marionette',
	'marionette.region.multi',
	'text!templates/Directory.html',
	'i18n!nls/report',
	'text!templates/Basename.html',
	'text!templates/Breadcrumb.html',

	'bootstrap.dropdown'
], function(Marionette, RegionMulti, Template, Lang, BasenameTemplate, BreadcrumbTemplate) {

	var Basename = Marionette.ItemView.extend({
		className: 'du-basename',
		template: _.template(BasenameTemplate, null, { variable: 'args' }),
		serializeData: function() {
			return _.extend({
				settings: this.model.settings,
				Lang: Lang
			}, Marionette.Layout.prototype.serializeData.apply(this, arguments));
		},
		onRender: function() {
			this.$el.find('h2').dropdown();
		}
	});

	var Breadcrumb = Marionette.ItemView.extend({
		className: 'du-breadcrumb',
		template: _.template(BreadcrumbTemplate, null, { variable: 'args' }),
		serializeData: function() {
			return _.extend({
				settings: this.model.settings,
				Lang: Lang
			}, Marionette.Layout.prototype.serializeData.apply(this, arguments));
		}
	});

	var SummarySize = Marionette.ItemView.extend({
		className: 'du-summary-size',
		template: _.template(Lang.total_size),
		serializeData: function() {
			var model = this.model;
			return {
				total: _.escape(Lang.formatBytes(model.get('fileSize'))),
				direct: _.escape(Lang.formatBytes(model.get('directFileSize'))),
				sub: _.escape(Lang.formatBytes(model.get('subFileSize'))),
				totalRaw: model.get('fileSize'),
				directRaw: model.get('directFileSize'),
				subRaw: model.get('subFileSize')
			};
		}
	});

	var SummaryCount = Marionette.ItemView.extend({
		className: 'du-summary-count',
		template: _.template(Lang.total_files),
		serializeData: function() {
			var model = this.model;
			return {
				total: _.escape(Lang.formatNumber(model.get('fileCount'))),
				direct: _.escape(Lang.formatNumber(model.get('directFileCount'))),
				sub: _.escape(Lang.formatNumber(model.get('subFileCount'))),
				totalRaw: model.get('fileCount'),
				directRaw: model.get('directFileCount'),
				subRaw: model.get('subFileCount')
			};
		}
	});

	return Marionette.Layout.extend({
		className: 'du-directory',
		template: _.template(Template),
		initialize: function() {
			Marionette.Layout.prototype.initialize.apply(this, arguments);
			this.addRegions({
				header: {
					selector: '.du-header',
					parentEl: this.$el,
					regionType: RegionMulti
				},
				summary: {
					selector: '.du-summary',
					parentEl: this.$el,
					regionType: RegionMulti
				}
			});
		},
		onRender: function() {
			this.header.show([
				new Basename({ model: this.model }),
				new Breadcrumb({ model: this.model })
			]);
			this.summary.show([
				new SummarySize({ model: this.model }),
				new SummaryCount({ model: this.model })
			]);
		}
	});
});
