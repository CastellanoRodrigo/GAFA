<?php
// Configuracion de avisos por mail y WhatsApp.
// Copiar este archivo como config/notificaciones.php y completar los datos reales.
// config/notificaciones.php NO se sube a git (contiene claves).
return [
    'empresa' => 'Don Juan S.R.L',

    // Direccion publica del sistema, usada en los enlaces de recuperacion de contraseña. Ej.: 'https://gestion.miempresa.com/GAFA'
    // Si se deja vacia se arma con la direccion del pedido.
    'url_base' => '',

    // A quien le llega el resumen diario de vencimientos (el dueño / administracion)
    'aviso_interno_email' => '',
    'aviso_interno_telefono' => '',

    // Cuando avisar
    'dias_cuota_proxima' => 7,      // avisar cuotas que vencen en los proximos N dias
    'dias_contrato_por_vencer' => 60, // avisar contratos que terminan en los proximos N dias
    'reenviar_cada_dias' => 7,      // no repetir el mismo aviso al mismo destinatario antes de N dias

    'email' => [
        // 'archivo' = no envia: guarda el mensaje en storage/outbox (para probar)
        // 'smtp'    = envia por un servidor SMTP (Gmail, Outlook, el de su hosting)
        // 'mail'    = usa la funcion mail() de PHP (solo si el servidor esta configurado)
        'driver' => 'archivo',
        'remitente' => 'avisos@sudominio.com',
        'nombre_remitente' => 'Don Juan S.R.L',
        'host' => 'smtp.gmail.com',
        'puerto' => 587,
        'seguridad' => 'tls',       // tls (587), ssl (465) o none
        'usuario' => '',
        'clave' => '',              // en Gmail: "contraseña de aplicacion"
        'carpeta_archivo' => __DIR__ . '/../storage/outbox',
    ],

    'whatsapp' => [
        // 'enlace'    = abre WhatsApp con el mensaje ya escrito (un clic para enviar). No requiere cuenta de empresa.
        // 'cloud_api' = envio automatico con WhatsApp Business Cloud API (requiere cuenta Meta y plantillas aprobadas)
        'driver' => 'enlace',
        'codigo_pais' => '54',      // Argentina
        'token' => '',
        'phone_number_id' => '',
    ],
];
