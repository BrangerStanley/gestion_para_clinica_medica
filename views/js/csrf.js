// js/csrf.js
// Interceptor global de fetch: agrega el token CSRF a todas las peticiones POST.
// El token se lee del <meta name="csrf-token"> que inyecta cada vista protegida.
// FormData     → se agrega campo csrftoken
// urlencoded   → se agrega campo csrftoken
// JSON         → se envía la cabecera X-CSRF-Token (la lee csrf_verify())
(function () {
    if (window.__iaaCsrfInstalled) return;
    window.__iaaCsrfInstalled = true;

    // Ruta base del sistema (inyectada por cada vista con <meta name="base-url">).
    var metaBase = document.querySelector('meta[name="base-url"]');
    window.BASE_URL = (metaBase && metaBase.content) ? metaBase.content : '/IAA';

    function obtenerToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : (window.IAA_CSRF_TOKEN || '');
    }

    function esPost(opts) {
        var m = (opts && opts.method) || 'GET';
        return String(m).toUpperCase() === 'POST';
    }

    var fetchOriginal = window.fetch;
    window.fetch = function (url, opts) {
        opts = opts || {};
        if (esPost(opts)) {
            var token = obtenerToken();
            if (token) {
                var headers = opts.headers || {};
                if (headers instanceof Headers) {
                    if (!headers.has('X-CSRF-Token')) headers.append('X-CSRF-Token', token);
                } else if (!headers['X-CSRF-Token']) {
                    headers['X-CSRF-Token'] = token;
                    opts.headers = headers;
                }

                var body = opts.body;
                var ct = '';
                if (headers instanceof Headers) {
                    ct = headers.get('Content-Type') || '';
                } else if (typeof headers === 'object') {
                    ct = headers['Content-Type'] || '';
                }

                if (body instanceof FormData) {
                    if (!body.has('csrftoken')) body.append('csrftoken', token);
                } else if (typeof body === 'string') {
                    if (ct.indexOf('application/json') !== -1) {
                        // ya viaja por cabecera
                    } else {
                        opts.body = (body ? body + '&' : '') + 'csrftoken=' + encodeURIComponent(token);
                    }
                }
            }
        }
        return fetchOriginal.call(this, url, opts);
    };
})();

// Utilidades compartidas de renderizado seguro
function escapeHtml(valor) {
    return String(valor == null ? '' : valor)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Escapado para valores insertados dentro de atributos onclick/JS:
// las entidades HTML se decodifican al evaluarse el atributo, por eso aquí
// se escapan la comilla y la barra (contexto JS), no entidades HTML.
function escapeJs(valor) {
    return String(valor == null ? '' : valor)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"')
        .replace(/</g, '\\u003c')
        .replace(/>/g, '\\u003e')
        .replace(/\r?\n/g, '\\n');
}