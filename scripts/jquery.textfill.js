; (function($) {
	$.fn.textfill = function(options) {
		var defaults = {
			maxFontPixels: 40,
			innerTag: 'span'
		};
		var Opts = jQuery.extend(defaults, options);
		return this.each(function() {
			var fontSize = Opts.maxFontPixels;
			var ourText = $(Opts.innerTag + ':visible:first', this);
            var wpadding = $(this).innerWidth() - $(this).width();
            var hpadding = $(this).innerHeight() - $(this).height();
			var maxHeight = $(this).height() - hpadding;
			var maxWidth = $(this).width() - wpadding;
			var textHeight;
			var textWidth;
			do {
				ourText.css('font-size', fontSize);
				textHeight = ourText.height() + hpadding;
				textWidth = ourText.textWidth() + wpadding;
				fontSize = fontSize - .5;
			} while ((textHeight > maxHeight || textWidth > maxWidth) && fontSize > 9);
		});
	};
    $.fn.textWidth = function(){
      var html_org = $(this).html();
      var html_calc = '<span>' + html_org + '</span>';
      $(this).html(html_calc);
      var width = $(this).find('span:first').width();
      $(this).html(html_org);
      return width+10;
    };
})(jQuery);