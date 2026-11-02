// HOWTO: load LABjs itself dynamically!
// inline this code in your page to load LABjs itself dynamically, if you're so inclined.
(function (global, oDOC, handler) {
	var head = oDOC.head || oDOC.getElementsByTagName("head"),

	// javascript core initialization
	LABjsLoaded = function() {
		// Config Copy
		if(global.preloader.CFG)
			for(var e in global.preloader.CFG)
				donrey.CFG[e] = global.preloader.CFG[e];
		// Extra DRY Init
		donrey.init();
		// initial scripts load and init
		for(var sc = 0; sc < global.preloader.load.length; sc++) {
			donrey.LAB.queueScript(global.preloader.load[sc]);
		}
		if(global.preloader.init)
			donrey.LAB.queueWait(global.preloader.init);
		donrey.LAB.runQueue();
	};

	// loading code borrowed directly from LABjs itself
	setTimeout(function () {
		if ("item" in head) { // check if ref is still a live node list
			if (!head[0]) { // append_to node not yet ready
				setTimeout(arguments.callee, 17);
				return;
			}
			head = head[0]; // reassign from live node list ref to pure node ref -- avoids nasty IE bug where changes to DOM invalidate live node lists
		}
		var scriptElem = oDOC.createElement("script"),
			scriptdone = false;
		scriptElem.onload = scriptElem.onreadystatechange = function () {
			if ((scriptElem.readyState && scriptElem.readyState !== "complete" && scriptElem.readyState !== "loaded") || scriptdone) {
				return false;
			}
			scriptElem.onload = scriptElem.onreadystatechange = null;
			scriptdone = true;
			LABjsLoaded();
		};
		scriptElem.src = global.preloader.DRY;
		head.insertBefore(scriptElem, head.firstChild);
	}, 17);

	// required: shim for FF <= 3.5 not having document.readyState
	if (oDOC.readyState == null && oDOC.addEventListener) {
		oDOC.readyState = "loading";
		oDOC.addEventListener("DOMContentLoaded", handler = function () {
			oDOC.removeEventListener("DOMContentLoaded", handler, false);
			oDOC.readyState = "complete";
		}, false);
	}
})(this, document);