// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Spanish Castilian (`es`).
class L10nEs extends L10n {
  L10nEs([String locale = 'es']) : super(locale);

  @override
  String get navSearch => 'Buscar';

  @override
  String get navMyBookings => 'Mis reservas';

  @override
  String get navWallet => 'Monedero';

  @override
  String get navProfile => 'Perfil';

  @override
  String get navLogin => 'Iniciar sesión';

  @override
  String get navRegister => 'Crear cuenta';

  @override
  String get navLogout => 'Cerrar sesión';

  @override
  String get navLanguage => 'Idioma';

  @override
  String get commonLoading => 'Cargando…';

  @override
  String get commonBack => 'Volver';

  @override
  String get commonOptional => 'opcional';

  @override
  String commonTimesNote(String tz) {
    return 'Todas las horas se muestran en $tz.';
  }

  @override
  String get searchTitle => 'Encuentra una moto o un coche';

  @override
  String get searchCountry => 'País';

  @override
  String get searchCity => 'Ciudad';

  @override
  String get searchAny => 'Cualquiera';

  @override
  String get searchPickUp => 'Recogida';

  @override
  String get searchReturn => 'Devolución';

  @override
  String get searchCategory => 'Categoría';

  @override
  String get searchType => 'Tipo';

  @override
  String get searchBike => 'Moto';

  @override
  String get searchCar => 'Coche';

  @override
  String get searchSeats => 'Plazas mín.';

  @override
  String get searchButton => 'Buscar';

  @override
  String get searchNoResults =>
      'Ningún vehículo coincide con tu búsqueda. Prueba otras fechas o filtros.';

  @override
  String get searchDatesInvalid =>
      'La devolución debe ser posterior a la recogida.';

  @override
  String get searchTotalForDates => 'total para tus fechas';

  @override
  String get searchPickDatesForPrice =>
      'Elige recogida y devolución para ver el precio.';

  @override
  String vehicleSeats(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n plazas',
      one: '$n plaza',
    );
    return '$_temp0';
  }

  @override
  String get vehicleSeatsLabel => 'Plazas';

  @override
  String get vehicleTransmission => 'Transmisión';

  @override
  String get vehicleFuel => 'Combustible';

  @override
  String get vehicleBranch => 'Sucursal';

  @override
  String get vehicleAvailable => 'Disponible en estas fechas';

  @override
  String get vehicleUnavailable => 'No disponible en estas fechas';

  @override
  String get vehicleAddons => 'Extras';

  @override
  String get vehicleAddonPerDay => 'por día';

  @override
  String get vehicleAddonPerBooking => 'por reserva';

  @override
  String get vehicleQty => 'Cantidad';

  @override
  String get vehicleCoupon => 'Código de cupón';

  @override
  String get vehicleQuote => 'Tu precio';

  @override
  String get vehicleSubtotal => 'Subtotal';

  @override
  String get vehicleDiscounts => 'Descuentos';

  @override
  String get vehicleTax => 'Impuestos';

  @override
  String get vehicleTotal => 'Total';

  @override
  String get vehicleDeposit => 'Depósito reembolsable';

  @override
  String get vehicleDueNow => 'A pagar ahora';

  @override
  String get vehicleBookNow => 'Reservar ahora';

  @override
  String get vehicleLoginToBook => 'Inicia sesión para reservar';

  @override
  String get vehiclePriceUnavailable => 'Sin precio para estas fechas';

  @override
  String get authSignIn => 'Iniciar sesión';

  @override
  String get authWithPassword => 'Contraseña';

  @override
  String get authWithOtp => 'Código por SMS';

  @override
  String get authEmail => 'Correo electrónico';

  @override
  String get authPassword => 'Contraseña';

  @override
  String get authPasswordConfirmation => 'Confirmar contraseña';

  @override
  String get authName => 'Nombre completo';

  @override
  String get authPhone => 'Teléfono';

  @override
  String get authPhoneHint => 'Formato internacional, p. ej. +34600000000';

  @override
  String get authCode => 'Código de 6 dígitos';

  @override
  String get authSendCode => 'Enviar código';

  @override
  String get authVerify => 'Verificar e iniciar sesión';

  @override
  String get authNoAccount => '¿Eres nuevo?';

  @override
  String get authHaveAccount => '¿Ya tienes cuenta?';

  @override
  String get authCreateAccount => 'Crear cuenta';

  @override
  String get authTooMany =>
      'Demasiados intentos. Espera un minuto e inténtalo de nuevo.';

  @override
  String get checkoutTitle => 'Pago';

  @override
  String get checkoutPay => 'Pagar';

  @override
  String get checkoutPayWallet => 'Pagar con el monedero';

  @override
  String get checkoutPayStripe => 'Pagar con tarjeta (Stripe)';

  @override
  String get checkoutPayRazorpay => 'Pagar con Razorpay';

  @override
  String get checkoutNoCard =>
      'El pago con tarjeta no está disponible ahora mismo.';

  @override
  String checkoutHoldNote(String time) {
    return 'Te reservamos este vehículo hasta las $time.';
  }

  @override
  String get confirmationTitle => 'Estado de la reserva';

  @override
  String get confirmationConfirmed => 'Tu reserva está confirmada.';

  @override
  String get confirmationWaiting =>
      'Esperando la confirmación del pago. Esta página se actualiza sola.';

  @override
  String get confirmationFailed =>
      'Esta reserva se ha cancelado. El dinero recibido se ha devuelto a tu monedero.';

  @override
  String get confirmationCardFailed =>
      'El pago con tarjeta no se completó. Puedes intentarlo de nuevo.';

  @override
  String get confirmationViewBooking => 'Ver reserva';

  @override
  String bookingTitle(String id) {
    return 'Reserva n.º $id';
  }

  @override
  String get bookingNone => 'Todavía no tienes reservas.';

  @override
  String get bookingAmountDue => 'Importe pendiente';

  @override
  String get bookingAmountPaid => 'Pagado';

  @override
  String get bookingPayBalance => 'Pagar ahora';

  @override
  String get bookingNewEnd => 'Nueva hora de devolución';

  @override
  String get bookingExtend => 'Ampliar reserva';

  @override
  String get bookingCancel => 'Cancelar reserva';

  @override
  String get bookingCancelConfirm =>
      '¿Cancelar esta reserva? Todo lo que pagaste vuelve a tu monedero.';

  @override
  String get bookingInvoice => 'Descargar factura (PDF)';

  @override
  String get bookingDepositStatus => 'Depósito';

  @override
  String get bookingCancelledNotice =>
      'Reserva cancelada. El reembolso está en tu monedero.';

  @override
  String get bookingExtendedNotice => 'Reserva ampliada.';

  @override
  String get depositHeld => 'retenido';

  @override
  String get depositReleased => 'devuelto';

  @override
  String get statusDraft => 'Pendiente de pago';

  @override
  String get statusPendingPayment => 'Pago en curso';

  @override
  String get statusConfirmed => 'Confirmada';

  @override
  String get statusPickedUp => 'En alquiler';

  @override
  String get statusReturned => 'Devuelto';

  @override
  String get statusCompleted => 'Completada';

  @override
  String get statusSettled => 'Cerrada';

  @override
  String get statusCancelled => 'Cancelada';

  @override
  String get statusNoShow => 'No presentado';

  @override
  String get walletBalance => 'Saldo';

  @override
  String get walletEmpty => 'Tu monedero está vacío.';

  @override
  String get walletNoTransactions => 'Aún no hay movimientos.';

  @override
  String get walletTopUp => 'Recargar';

  @override
  String get walletAmount => 'Importe';

  @override
  String get walletCurrency => 'Moneda';

  @override
  String get walletDate => 'Fecha';

  @override
  String get walletType => 'Tipo';

  @override
  String get walletProcessing =>
      'Completa el pago en la ventana que se abrió. Tu saldo se actualiza cuando el proveedor de pagos lo confirma.';

  @override
  String get walletAmountInvalid => 'Introduce un importe válido.';

  @override
  String get walletTypeTopup => 'Recarga';

  @override
  String get walletTypePayment => 'Pago de reserva';

  @override
  String get walletTypeRefund => 'Reembolso';

  @override
  String get walletTypeDepositRelease => 'Depósito devuelto';

  @override
  String get profileSave => 'Guardar';

  @override
  String get profileSaved => 'Perfil guardado.';

  @override
  String get draftExpired =>
      'Este borrador de reserva ha caducado. Inicia una nueva reserva.';

  @override
  String get walletTooLow =>
      'El saldo de tu monedero no es suficiente para este pago.';

  @override
  String get errorNetwork =>
      'No se puede conectar con el servidor. Revisa tu conexión.';

  @override
  String get retry => 'Reintentar';

  @override
  String get paymentNotCompleted => 'El pago no se completó.';

  @override
  String invoiceSaved(String path) {
    return 'Factura guardada en $path';
  }

  @override
  String get signInToContinue => 'Inicia sesión para continuar';

  @override
  String get selectDate => 'Elige fecha y hora';

  @override
  String get statusExpired => 'Caducada';

  @override
  String get bookAgain => 'Reservar de nuevo';
}
