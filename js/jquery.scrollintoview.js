/**
 * @author amekkawi
 */

;(function($) {
	$.fn.scrollIntoView = function(target, duration) {
		if (this.size == 0) return;
		if ($.isUndefined(duration)) duration = 0;
		
		if ($.isJQuery(target) && target.size() == 0) return;
		else if (!$.isJQuery(target) && !$.isElement(target)) return;
		
		// Make sure the target is jQuery
		target = $(target);
		
		var container = this.eq(0),
			targetTop = Math.round(target.offset().top),
			targetLeft = Math.round(target.offset().left) + 14, /* +14 is custom for disk usage reporting */
			targetWidth = target.outerWidth() - 14, /* -14 is custom for disk usage reporting */
			targetHeight = target.outerHeight(),
			containerScrollTop = Math.round(container.scrollTop()),
			containerScrollLeft = Math.round(container.scrollLeft()),
			containerHeight = Math.round(container.height()) - (container.get(0).clientWidth < container.get(0).scrollWidth ? $.scrollbarWidth() : 0) - 4, /* -4 is custom for disk usage reporting */
			containerWidth = Math.round(container.width()) - (container.get(0).clientHeight < container.get(0).scrollHeight ? $.scrollbarWidth() : 0) - 4; /* -4 is custom for disk usage reporting */
		
		// Calculate the scroll values for the target.
		var scrollTop = Math.round(targetTop) + ( containerScrollTop - Math.round(container.offset().top) );
		var scrollLeft = Math.round(targetLeft) + ( containerScrollLeft - Math.round(container.offset().left) );
		
		// Do not continue if the target is visible.
		if (scrollTop >= containerScrollTop && (scrollTop + targetHeight) <= (containerScrollTop + containerHeight))
			scrollTop = null; 
		
		// Do not continue if the target is visible.
		if (scrollLeft >= containerScrollLeft && (scrollLeft + targetWidth) <= (containerScrollLeft + containerWidth))
			scrollLeft = null; 
		
		// If it is after the fold, make sure the target is scrolled to the bottom of the container instead.
		if (scrollTop != null && scrollTop > containerScrollTop && containerHeight > targetHeight) {
			scrollTop -= containerHeight - targetHeight;
		}
		
		// If it is after the fold, make sure the target is scrolled to the right of the container instead.
		if (scrollLeft != null && scrollLeft > containerScrollLeft && containerWidth > targetWidth) {
			scrollLeft -= containerWidth - targetWidth;
		}
		
		if (duration && duration > 0) {
			//container.animate({ 'scrollTop': scrollTop }, duration, 'swing');
		}
		else {
			if (scrollTop != null) container.scrollTop(scrollTop);
			if (scrollLeft != null) container.scrollLeft(scrollLeft);
		}
	};
})(jQuery);
