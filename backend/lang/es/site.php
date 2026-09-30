<?php

return [
    'nav' => ['search' => 'Buscar', 'my_bookings' => 'Mis reservas', 'wallet' => 'Monedero', 'profile' => 'Perfil', 'login' => 'Iniciar sesión', 'register' => 'Crear cuenta', 'logout' => 'Cerrar sesión', 'language' => 'Idioma'],
    'common' => ['loading' => 'Cargando…', 'back' => 'Volver', 'optional' => 'opcional', 'times_note' => 'Todas las horas se muestran en :tz.'],
    'search' => [
        'title' => 'Encuentra una moto o un coche', 'country' => 'País', 'city' => 'Ciudad', 'any' => 'Cualquiera', 'pick_up' => 'Recogida', 'return' => 'Devolución',
        'category' => 'Categoría', 'type' => 'Tipo', 'bike' => 'Moto', 'car' => 'Coche', 'seats' => 'Plazas mín.', 'button' => 'Buscar',
        'no_results' => 'Ningún vehículo coincide con tu búsqueda. Prueba otras fechas o filtros.', 'dates_invalid' => 'La devolución debe ser posterior a la recogida.',
        'total_for_dates' => 'total para tus fechas', 'pick_dates_for_price' => 'Elige recogida y devolución para ver el precio.',
    ],
    'vehicle' => [
        'seats' => ':n plaza|:n plazas', 'seats_label' => 'Plazas', 'transmission' => 'Transmisión', 'fuel' => 'Combustible', 'branch' => 'Sucursal',
        'available' => 'Disponible en estas fechas', 'unavailable' => 'No disponible en estas fechas', 'addons' => 'Extras',
        'addon_per_day' => 'por día', 'addon_per_booking' => 'por reserva', 'qty' => 'Cantidad', 'coupon' => 'Código de cupón', 'quote' => 'Tu precio',
        'subtotal' => 'Subtotal', 'discounts' => 'Descuentos', 'tax' => 'Impuestos', 'total' => 'Total', 'deposit' => 'Depósito reembolsable', 'due_now' => 'A pagar ahora',
        'book_now' => 'Reservar ahora', 'login_to_book' => 'Inicia sesión para reservar', 'price_unavailable' => 'Sin precio para estas fechas',
    ],
    'auth' => [
        'sign_in' => 'Iniciar sesión', 'with_password' => 'Contraseña', 'with_otp' => 'Código por SMS', 'email' => 'Correo electrónico', 'password' => 'Contraseña',
        'password_confirmation' => 'Confirmar contraseña', 'name' => 'Nombre completo', 'phone' => 'Teléfono', 'phone_hint' => 'Formato internacional, p. ej. +34600000000',
        'code' => 'Código de 6 dígitos', 'send_code' => 'Enviar código', 'verify' => 'Verificar e iniciar sesión', 'no_account' => '¿Eres nuevo?',
        'have_account' => '¿Ya tienes cuenta?', 'create_account' => 'Crear cuenta', 'too_many' => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.',
    ],
    'checkout' => [
        'title' => 'Pago', 'pay' => 'Pagar', 'pay_wallet' => 'Pagar con el monedero', 'pay_stripe' => 'Pagar con tarjeta (Stripe)', 'pay_razorpay' => 'Pagar con Razorpay',
        'no_card' => 'El pago con tarjeta no está disponible ahora mismo.', 'hold_note' => 'Te reservamos este vehículo hasta las :time.',
    ],
    'confirmation' => [
        'title' => 'Estado de la reserva', 'confirmed' => 'Tu reserva está confirmada.', 'waiting' => 'Esperando la confirmación del pago. Esta página se actualiza sola.',
        'failed' => 'Esta reserva se ha cancelado. El dinero recibido se ha devuelto a tu monedero.', 'card_failed' => 'El pago con tarjeta no se completó. Puedes intentarlo de nuevo.',
        'view_booking' => 'Ver reserva',
    ],
    'booking' => [
        'title' => 'Reserva n.º :id', 'none' => 'Todavía no tienes reservas.', 'amount_due' => 'Importe pendiente', 'amount_paid' => 'Pagado', 'pay_balance' => 'Pagar ahora',
        'new_end' => 'Nueva hora de devolución', 'extend' => 'Ampliar reserva', 'cancel' => 'Cancelar reserva', 'cancel_confirm' => '¿Cancelar esta reserva? Todo lo que pagaste vuelve a tu monedero.',
        'invoice' => 'Descargar factura (PDF)', 'deposit_status' => 'Depósito', 'cancelled_notice' => 'Reserva cancelada. El reembolso está en tu monedero.',
        'extended_notice' => 'Reserva ampliada.',
    ],
    'deposit' => ['held' => 'retenido', 'released' => 'devuelto'],
    'status' => [
        'draft' => 'Pendiente de pago', 'pending_payment' => 'Pago en curso', 'confirmed' => 'Confirmada', 'picked_up' => 'En alquiler', 'returned' => 'Devuelto',
        'completed' => 'Completada', 'settled' => 'Cerrada', 'cancelled' => 'Cancelada', 'no_show' => 'No presentado',
    ],
    'wallet' => [
        'balance' => 'Saldo', 'empty' => 'Tu monedero está vacío.', 'no_transactions' => 'Aún no hay movimientos.', 'top_up' => 'Recargar', 'amount' => 'Importe',
        'currency' => 'Moneda', 'date' => 'Fecha', 'type' => 'Tipo', 'processing' => 'Completa el pago en la ventana que se abrió. Tu saldo se actualiza cuando el proveedor de pagos lo confirma.',
        'amount_invalid' => 'Introduce un importe válido.', 'type_topup' => 'Recarga', 'type_payment' => 'Pago de reserva', 'type_refund' => 'Reembolso', 'type_deposit_release' => 'Depósito devuelto',
    ],
    'profile' => ['save' => 'Guardar', 'saved' => 'Perfil guardado.'],
];
