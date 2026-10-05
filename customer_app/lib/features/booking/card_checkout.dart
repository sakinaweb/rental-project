import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

/// Opens the gateway's checkout for an intent's `client` data. Returns true when the customer finished the
/// checkout, false when it failed or was dismissed. This never confirms anything: the booking/top-up is only
/// confirmed by the backend webhook, which the confirmation screen waits for.
typedef CardCheckout = Future<bool> Function(String gateway, Map<String, dynamic> client, {required String title});

final cardCheckoutProvider = Provider<CardCheckout>((_) => _open);

Future<bool> _open(String gateway, Map<String, dynamic> client, {required String title}) {
  // India pack (MD §7.7 "one country pack end to end"): Razorpay. Stripe joins with the other packs (Phase 8).
  if (gateway != 'razorpay') return Future.value(false);

  final done = Completer<bool>();
  final razorpay = Razorpay();
  razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, (_) => done.complete(true));
  razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, (_) => done.complete(false));
  razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, (_) => done.complete(true));
  razorpay.open({'key': client['key_id'], 'order_id': client['order_id'], 'name': title});

  return done.future.whenComplete(razorpay.clear);
}
