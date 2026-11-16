/**
 * IAA · Responsive global
 * ------------------------------------------------------------
 * Controla la barra lateral desplegable en TODAS las vistas:
 *
 *   · Inserta el botón hamburguesa en el header si la vista no lo trae.
 *   · Inserta el fondo oscurecido y el botón de cierre del panel.
 *   · El panel arranca siempre recogido.
 *   · Se cierra con Esc, con el fondo, con el botón de cierre y al
 *     navegar a otra página.
 *
 * No depende de jQuery ni de Bootstrap.
 */
(function () {
    'use strict';

    var sidebar = null;
    var toggle = null;
    var backdrop = null;

    function crearBotonHamburguesa() {
        var header = document.querySelector('.header');
        if (!header) return null;

        var contenedor = header.querySelector(
            '.navbar .container-fluid, .navbar .container, .navbar'
        );
        if (!contenedor) return null;

        var boton = contenedor.querySelector('.iaa-menu-toggle');
        if (boton) return boton;

        boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'iaa-menu-toggle';
        boton.id = 'iaaMenuToggle';
        boton.setAttribute('aria-label', 'Abrir menú de navegación');
        boton.setAttribute('aria-controls', 'sidebar');
        boton.setAttribute('aria-expanded', 'false');
        boton.innerHTML = '<span></span><span></span><span></span>';

        contenedor.insertBefore(boton, contenedor.firstChild);
        return boton;
    }

    function crearCabeceraPanel(panel) {
        if (panel.querySelector('.iaa-sidebar-cabecera')) return;

        var cabecera = document.createElement('div');
        cabecera.className = 'iaa-sidebar-cabecera';
        cabecera.innerHTML =
            '<span class="iaa-sidebar-titulo">Menú</span>' +
            '<button type="button" class="iaa-sidebar-cerrar" aria-label="Cerrar menú">' +
            '<i class="bi bi-x-lg" aria-hidden="true"></i>' +
            '</button>';

        panel.insertBefore(cabecera, panel.firstChild);

        var cerrar = cabecera.querySelector('.iaa-sidebar-cerrar');
        cerrar.addEventListener('click', cerrarMenu);
    }

    function abrirMenu() {
        if (!sidebar) return;
        sidebar.classList.add('is-open');
        document.body.classList.add('iaa-sidebar-open');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-label', 'Cerrar menú de navegación');
        }
        // Enfocar el panel para que el teclado y los lectores de pantalla
        // permanezcan dentro del menú hasta que se cierre.
        sidebar.setAttribute('tabindex', '-1');
        sidebar.focus({ preventScroll: true });
    }

    function cerrarMenu() {
        if (!sidebar) return;
        sidebar.classList.remove('is-open');
        document.body.classList.remove('iaa-sidebar-open');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Abrir menú de navegación');
        }
    }

    function alternarMenu() {
        if (!sidebar) return;
        if (sidebar.classList.contains('is-open')) {
            cerrarMenu();
        } else {
            abrirMenu();
        }
    }

    function iniciar() {
        sidebar = document.getElementById('sidebar') ||
            document.querySelector('.barra-lateral');

        // Solo las páginas con menú lateral necesitan el drawer. Aun así,
        // la clase de layout se aplica si hay header (reserva su altura).
        if (document.querySelector('.header')) {
            document.body.classList.add('iaa-layout');
        }
        if (!sidebar) return;

        if (!sidebar.id) sidebar.id = 'sidebar';

        toggle = crearBotonHamburguesa();
        if (toggle) {
            toggle.addEventListener('click', alternarMenu);
        }

        crearCabeceraPanel(sidebar);

        backdrop = document.createElement('div');
        backdrop.className = 'iaa-sidebar-backdrop';
        backdrop.addEventListener('click', cerrarMenu);
        document.body.appendChild(backdrop);

        // Cerrar al pulsar un enlace de navegación real. Los toggles de
        // submenú (href="#") deben mantener el panel abierto.
        sidebar.addEventListener('click', function (evento) {
            var enlace = evento.target.closest('a');
            if (!enlace) return;
            var href = enlace.getAttribute('href') || '';
            if (href === '' || href === '#' || href.indexOf('javascript:') === 0) {
                return;
            }
            cerrarMenu();
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' || evento.key === 'Esc') {
                cerrarMenu();
            }
        });

        // Si se navega con el botón Atrás/Adelante del navegador, el panel
        // no debe quedar abierto sobre la página restaurada.
        window.addEventListener('pageshow', cerrarMenu);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
