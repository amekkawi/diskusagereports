;(function($) {
	$(function(){
		var win = $(window),
			toc = $('#TOC'),
			tocTop = toc.size() && toc.offset().top - 20,
			isFixed = 0;
		
		var processScroll = function() {
			var i, scrollTop = win.scrollTop();
			if (scrollTop >= tocTop && !isFixed) {
				isFixed = 1;
				toc.addClass('fixed');
			}
			else if (scrollTop <= tocTop && isFixed) {
				isFixed = 0;
				toc.removeClass('fixed');
			}
		};
		
		processScroll();
		win.on('scroll', processScroll);
	});
})(jQuery);