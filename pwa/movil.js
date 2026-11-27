// GAFA movil: tablas en tarjetas, aviso sin conexion, instalacion y service worker
(function () {
    'use strict';
    // 1) Cada celda recibe el titulo de su columna (para verlas como tarjetas en el celular)
    document.querySelectorAll('table').forEach(function (t) {
        if (t.closest('.sin-tarjetas')) return;
        var cab = t.querySelectorAll('thead th');
        if (!cab.length) { var p = t.querySelector('tr'); if (p) cab = p.querySelectorAll('th'); }
        if (!cab.length) return;
        var titulos = Array.prototype.map.call(cab, function (th) { return th.textContent.trim(); });
        t.classList.add('tabla-movil');
        t.querySelectorAll('tr').forEach(function (tr) {
            var i = 0;
            Array.prototype.forEach.call(tr.children, function (td) {
                if (td.tagName !== 'TD') return;
                td.setAttribute('data-label', titulos[i] || '');
                i += (td.colSpan || 1);
            });
        });
    });

    // 2) Aviso cuando se pierde la conexion
    function estado() { document.body.classList.toggle('sin-conexion', !navigator.onLine); }
    window.addEventListener('online', estado);
    window.addEventListener('offline', estado);
    estado();

    // 3) Instalar en la pantalla de inicio
    var evento = null, aviso = document.getElementById('aviso-instalar');
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault(); evento = e;
        if (aviso && !localStorage.getItem('gafa_instalar_no')) aviso.classList.add('visible');
    });
    if (aviso) {
        aviso.querySelector('.si').addEventListener('click', function () {
            aviso.classList.remove('visible');
            if (evento) { evento.prompt(); evento = null; }
        });
        aviso.querySelector('.no').addEventListener('click', function () {
            aviso.classList.remove('visible'); localStorage.setItem('gafa_instalar_no', '1');
        });
    }
    window.addEventListener('appinstalled', function () { if (aviso) aviso.classList.remove('visible'); });

    // 4) Service worker: solo guarda archivos estaticos y la pantalla "sin conexion"
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js').catch(function () {});
        });
    }
})();
