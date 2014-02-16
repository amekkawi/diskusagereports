define([
	'underscore',
	'marionette'
], function(_, Marionette) {
	return Marionette.Region.extend({
		show: function(views) {
			this.ensureEl();

			if (!_.isArray(views))
				views = [ views ];

			var currentViews = this.currentViews,
				openViews = false;

			// Close current views not in the new list.
			currentViews && _.each(currentViews, function(currentView) {
				if (_.indexOf(views) < 0)
					currentView.close();
			}, this);

			_.each(views, function(view) {
				// Open the views if the view has been closed.
				if (view.isClosed || _.isUndefined(view.$el))
					openViews = true;

				// Open the views if the view is not currently shown.
				if (!currentViews || _.indexOf(currentViews) < 0)
					openViews = true;

				view.render();
			}, this);

			if (openViews)
				this.open(views);

			this.currentViews = views;

			Marionette.triggerMethod.call(this, "show", views);
			_.each(views, function(view) {
				Marionette.triggerMethod.call(view, "show");
			}, this);
		},

		open: function(views) {
			this.$el.empty().append(_.map(views, function(view) { return view.el; }));
		},

		close: function(){
			var views = this.currentViews;
			if (!views || _.findIndex(views, function(view) { return !view.isClosed; }) < 0){ return; }

			_.each(views, function(view) {
				if (view.close) { view.close(); }
				else if (view.remove) { view.remove(); }
			}, this);

			Marionette.triggerMethod.call(this, "close", views);

			delete this.currentViews;
		},

		attachViews: function(views){
			this.currentViews = views;
		}
	});
});
