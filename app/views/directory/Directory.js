define([
	'underscore',
	'marionette',
	'marionette.region.multi',
	'tpl!views/directory/Directory.html',
	'views/directory/Basename',
	'views/directory/Breadcrumb',
	'views/directory/SummarySize',
	'views/directory/SummaryCount',
	'views/directory/Tabs',
	'views/directory/TabDirs',
	'views/directory/TabFiles',
	'views/directory/TabModified'
], function(
	_, Marionette, RegionMulti, Template,
	Basename, Breadcrumb, SummarySize, SummaryCount, Tabs,
	TabDirs, TabFiles, TabModified
) {
	'use strict';

	return Marionette.Layout.extend({
		className: 'du-directory',
		template: Template,

		constructor: function(options) {
			if (options)
				_.extend(this, _.pick(options, [ 'app' ]));

			Marionette.Layout.apply(this, arguments);
		},

		initialize: function(options) {
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
				},
				detail: {
					selector: '.du-detail',
					parentEl: this.$el,
					regionType: RegionMulti
				}
			});
		},

		onRender: function() {
			var app = this.app;
			var route = app.getRoute();
			var options = { app: app, model: this.model };

			this.header.show([
				new Basename(options),
				new Breadcrumb(options)
			]);

			this.summary.show([
				new SummarySize(options),
				new SummaryCount(options)
			]);

			var tabView = TabDirs;
			switch (route.tab) {
				case 'files':
					tabView = TabFiles;
					break;
				case 'modified':
					tabView = TabModified;
					break;
			}

			this.detail.show([
				new Tabs(options),
				new tabView(options)
			]);
		}
	});
});
