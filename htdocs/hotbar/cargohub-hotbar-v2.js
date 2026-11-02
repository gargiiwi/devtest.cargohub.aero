(function () {
    var css = document.createElement("link"),
        body = document.getElementsByTagName("body")[0],
        head = document.getElementsByTagName("head")[0],
        hotbar = document.createElement("div");

    css.setAttribute("rel", "stylesheet");
    css.setAttribute("type", "text/css");
    css.setAttribute("href", "/hotbar/cargohub-hotbar-v2.css?v=2026.10.13.0442");

    hotbar.id = "cargohub-hotbar";

    hotbar.innerHTML =
        '<header class="cc-header">' +
            '<div class="cc-header-top">' +
                '<div class="cc-container cc-header-inner" >' +

                    '<a href="/cms?path=worldwide" class="cc-logo">' +
                        '<img src="/img/cargoclaimlogoNew.png" alt="Cargo Claims">' +
                    '</a>' +

                    '<nav class="cc-nav">' +
                        '<a href="/cms?path=worldwide">Worldwide</a>' +
                        '<a href="/cms?path=/cargoclaims/docs/benefits_for_airlines" target="_blank">Airlines</a>' +
                        '<a href="https://cargohub.aero/cms?path=/cargoclaims/docs/benefits_for_claimants" target="_blank">Claimants</a>' +
                        '<a href="/cms?path=/cargoclaims/docs/benefits_for_handlers" target="_blank">Handlers</a>' +
                        '<a href="http://www.cargohub.nl/blog" target="_blank">Blog</a>' +
                    '</nav>' +

                    '<a href="/access?action=entrance" class="cc-login">' +
                        '<i class="fa fa-user"></i>' +
                        '<span>System User Login</span>' +
                    '</a>' +

                '</div>' +
            '</div>' +
        '</header>';

    head.appendChild(css);
    body.insertBefore(hotbar, body.firstChild);
})();