var $ = function(a) { return document.getElementById(a); };
var Event = {
	a: function() {
		var a = new Array();
		var x = document.getElementsByTagName("div");

		for (var j = 0; j < x.length; j++) {
			if (x[j].id.search(/in_+\d/) > -1) {
				a.push($(x[j].id));
			}
		}

		return a;
	},

	init: function() {
		var x = this.a();
		if(!$('master')) return;

		$('master').onclick = function() {
			if($('master').innerHTML == '[expand all]'){
				$('master').innerHTML = '[minimize all]';

				for (var j = 0; j < x.length; j++) {
					var y = $(x[j].id.replace('in', 'out'));
					y.style.display = 'block';
				}
			}
			else if($('master').innerHTML == '[minimize all]'){
				$('master').innerHTML = '[expand all]';

				for (var j = 0; j < x.length; j++) {
					var y = $(x[j].id.replace('in', 'out'));
					y.style.display = 'none';
				}

			}
		}

		for (var j = 0; j < x.length; j++) {
			x[j].onclick = function(e) {
				var y = $(this.id.replace('in', 'out'));

				if(y.style.display == 'none') {
					y.style.display = 'block';
				}
				else if(y.style.display == 'block') {
					y.style.display = 'none';
				}
			}
		}
	}
};

window.onload = function() {
	Event.init();
};