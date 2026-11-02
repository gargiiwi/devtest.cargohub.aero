/**
 * jquery.pwstrength http://matoilic.github.com/jquery.pwstrength
 *
 * @version v0.1.1
 * @author Mato Ilic <info@matoilic.ch>
 * @copyright 2013 Mato Ilic
 *
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 *
 * @modified by Wavetrex <wavetrex@gmail.com>
 */
;(function($) {
	$.pwstrength = function(password) {
		var score = 0, length = password.length, upperCase, lowerCase, digits, nonAlpha;

		if(length < 4)
			return 0; // Too short

		if(length < 8) score += 5;
		else if(length < 16) score += 10;
		else score += 18;

		lowerCase = password.match(/[a-z]/g);
		if(lowerCase) score += 1;

		upperCase = password.match(/[A-Z]/g);
		if(upperCase) score += 4;

		// Both lower and uppercase increase the score a bit
		if(upperCase && lowerCase) score += 3;

		// Digits add score only if combined with letters
		digits = password.match(/\d/g);
		if((lowerCase || upperCase) && digits && digits.length > 1) score += 6;

		// Non-Alpha only score 16 if there's at least 2 of them
		nonAlpha = password.match(/\W/g);
		if(nonAlpha) score += (nonAlpha.length > 2) ? 16 : 8;

		if(upperCase && lowerCase && digits && nonAlpha) score += 16;

		if(password.match(/\s/)) score += 10;

		if(score == 0) return 0;
		if(score < 12) return 1;
		if(score < 18) return 2;
		if(score < 30) return 3;
		if(score < 50) return 4;
		return 5;
	};

	function updateIndicator(event) {
		var strength = $.pwstrength($(this).val()), options = event.data, klass = options.classes[strength];

		options.indicator.removeClass(options.indicator.data('pwclass'));
		options.indicator.data('pwclass', klass);
		options.indicator.addClass(klass);
		options.indicator.find(options.label).html(options.texts[strength]);

		if(options.callback)
			options.callback(strength, this);
	}

	$.fn.pwstrength = function(options) {
		var options = $.extend({
			label: '.label',
			classes: ['pw-empty', 'pw-very-weak', 'pw-weak', 'pw-mediocre', 'pw-strong', 'pw-very-strong'],
			texts: ['Minimum 4 characters', 'My cat could guess that', 'Uh-oh. You can do better', 'Okay... almost good.', 'Lovely password is lovely!', '~#$ G0dL|k3 h4xX0r $#~'],
			callback: null
		}, options || {});
		options.indicator = $('#' + this.data('indicator'));

		return this.keyup(options, updateIndicator);
	};
})(jQuery);