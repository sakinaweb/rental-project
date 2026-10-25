// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Spanish Castilian (`es`).
class L10nEs extends L10n {
  L10nEs([String locale = 'es']) : super(locale);

  @override
  String get appTitle => 'Personal';

  @override
  String get signIn => 'Iniciar sesión';

  @override
  String get email => 'Correo electrónico';

  @override
  String get password => 'Contraseña';

  @override
  String get signOut => 'Cerrar sesión';

  @override
  String get notStaff =>
      'Esta app es para cuentas de personal. Inicia sesión con tu cuenta de personal.';

  @override
  String get errorNetwork =>
      'No se puede conectar con el servidor. Revisa tu conexión.';

  @override
  String get retry => 'Reintentar';

  @override
  String get today => 'Hoy';

  @override
  String get pickups => 'Entregas';

  @override
  String get returns => 'Devoluciones';

  @override
  String get noPickups => 'No hay entregas pendientes.';

  @override
  String get noReturns => 'No hay vehículos fuera.';

  @override
  String get late => 'Con retraso';

  @override
  String due(String time) {
    return 'Previsto $time';
  }

  @override
  String booking(String id) {
    return 'Reserva n.º $id';
  }

  @override
  String get customer => 'Cliente';

  @override
  String get vehicle => 'Vehículo';

  @override
  String get period => 'Periodo';

  @override
  String currentOdometer(String km) {
    return 'Cuentakilómetros ahora: $km';
  }

  @override
  String get handOver => 'Entregar';

  @override
  String get receive => 'Recibir devolución';

  @override
  String get handoverTitle => 'Lista de entrega';

  @override
  String get returnTitle => 'Lista de devolución';

  @override
  String get photosHint =>
      'Al menos 4 fotos: delante, detrás, izquierda y derecha.';

  @override
  String get addPhoto => 'Hacer foto';

  @override
  String photoCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n fotos',
      one: '1 foto',
    );
    return '$_temp0';
  }

  @override
  String get odometer => 'Cuentakilómetros';

  @override
  String fuel(String pct) {
    return 'Nivel de combustible: $pct %';
  }

  @override
  String get notes => 'Notas (opcional)';

  @override
  String get damages => 'Daños encontrados';

  @override
  String get addDamage => 'Añadir daño';

  @override
  String get damageDescription => 'Qué está dañado';

  @override
  String damageCost(String currency) {
    return 'Coste estimado ($currency)';
  }

  @override
  String get damageNote =>
      'El administrador revisa los daños antes de cobrar nada.';

  @override
  String get remove => 'Quitar';

  @override
  String get confirmHandover => 'Confirmar entrega';

  @override
  String get confirmReturn => 'Confirmar devolución';

  @override
  String get needPhotos => 'Haz al menos 4 fotos.';

  @override
  String get invalidNumber => 'Introduce un número entero.';

  @override
  String get done => 'Guardado.';

  @override
  String get checklists => 'Listas de control';

  @override
  String get stagePickup => 'Entrega';

  @override
  String get stageReturn => 'Devolución';

  @override
  String by(String name) {
    return 'por $name';
  }

  @override
  String status(String status) {
    return 'Estado: $status';
  }
}
