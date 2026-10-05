import 'dart:math';

import 'package:intl/intl.dart';

/// API amounts are integer minor units of a currency (MD §4); 354000 INR → ₹3,540.00.
String formatMoney(int minor, String currency, String locale) {
  final format = NumberFormat.simpleCurrency(locale: locale, name: currency);
  return format.format(minor / pow(10, format.decimalDigits ?? 2));
}

/// "1500.50" typed by the customer → 150050 minor units. Null when not a positive number.
int? toMinor(String amount, String currency) {
  final value = double.tryParse(amount.trim().replaceAll(',', '.'));
  if (value == null || value <= 0) return null;
  final digits = NumberFormat.simpleCurrency(name: currency).decimalDigits ?? 2;
  return (value * pow(10, digits)).round();
}
