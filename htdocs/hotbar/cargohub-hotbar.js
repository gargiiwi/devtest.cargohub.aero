(function(){
	var x = document.createElement("link"), w = document.getElementsByTagName("head")[0], y = document.createElement('div'), z = document.getElementsByTagName("body")[0];
	x.setAttribute("rel", "stylesheet"); x.setAttribute("type", "text/css");
	x.setAttribute("href", "/hotbar/cargohub-hotbar.css");
	y.id = 'cargohub-hotbar';
	y.innerHTML = ''+
'<div>'+
	'<a href="https://www.cargoclaims.aero/cms?path=worldwide"><img src="/hotbar/cargoclaims-logo-64.png" alt="Cargo/Claims by CargoHub"></a>'+
	// break
	'<a class="hotbar-link right" href="http://www.cargohub.nl/contactus" target="_blank">Contact</a>'+
	'<a class="hotbar-link right" href="http://www.cargohub.nl/blog" target="_blank">Blog</a>'+
	// '<a class="hotbar-link right" href="https://www.cargocommunity.nl/" target="_blank">Community <span class="red">(Beta)</span></a>'+
	'<div class="hotbar-link right" style="width: 1px; height: 30px; background-color: lightgray;"></div>'+
	'<a class="hotbar-link right" href="http://www.cargohub.nl/liability-calculators" target="_blank">Liability Calculators</a>'+
	'<a class="hotbar-link right" href="https://cargoclaims.aero/cms?path=/cargoclaims/docs/benefits_for_handlers" target="_blank">For Handlers</a>'+
	'<a class="hotbar-link right" href="https://cargohub.aero/cms?path=/cargoclaims/docs/benefits_for_claimants" target="_blank" target="_blank">For Claimants</a>'+
	'<a class="hotbar-link right" href="https://cargoclaims.aero/cms?path=/cargoclaims/docs/benefits_for_airlines" target="_blank">For Airlines</a>'+
	'<div class="hotbar-clear"></div>'+
'</div>';
	w.appendChild(x);
	z.insertBefore(y, z.firstChild);
})();
/*
'<a class="hotbar-link" href="/cms?path=marketing/solution">Solution</a>'+
'<a class="hotbar-link" href="/cms?path=help/faq">FAQ</a>'+
'<a class="hotbar-link" href="https://www.youtube.com/watch?v=zsjSkHMco6U" target="_blank">Presentation</a>'+
'<a class="hotbar-link" href="/cms?path=marketing/flyer">Brochure</a>'+
'<div id="hotbar-social">'+
'	<a title="LinkedIn - cargo-hub-schiphol" class="in" target="_blank" href=http://www.linkedin.com/groups?gid=3852592&trk=hb_side_g">LinkedIn - cargo-hub-schiphol</a>'+
'	<a title="Twitter @cargohub_europe" class="tw" target="_blank" href="http://twitter.com/#!/cargohub_europe">Twitter @cargohub_europe</a>'+
'	<a title="Facebook/CargoHub" class="fb" target="_blank" href="http://www.facebook.com/CargoHub">Facebook/CargoHub</a>'+
'</div>'+
*/