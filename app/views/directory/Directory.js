define([
	'underscore',
	'marionette',
	'marionette.region.multi',
	'tpl!views/directory/Directory.html',
	'views/directory/Basename',
	'views/directory/Breadcrumb',
	'views/directory/SummarySize',
	'views/directory/SummaryCount',
	'views/directory/Tabs'
], function(_, Marionette, RegionMulti, Template, Basename, Breadcrumb, SummarySize, SummaryCount, Tabs) {
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
			var options = _.pick(this, ['model', 'app']);

			this.header.show([
				new Basename(options),
				new Breadcrumb(options)
			]);

			this.summary.show([
				new SummarySize(options),
				new SummaryCount(options)
			]);

			this.detail.show([
				new Tabs(options)
			]);
		}
	});
});
