import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

import '../../l10n/app_localizations.dart';

/// Opens the gateway's checkout for an intent's `client` data. Returns true when the customer finished the
/// checkout, false when it failed or was dismissed. This never confirms anything: the booking/top-up is only
/// confirmed by the backend webhook, which the confirmation screen waits for.
typedef CardCheckout = Future<bool> Function(String gateway, Map<String, dynamic> client, {required String title});

final cardCheckoutProvider = Provider<CardCheckout>((_) => _open);

/// Gateways in the order the app shows them; the server decides which one a country offers (Phase 8).
const cardGateways = ['razorpay', 'stripe'];

String payWith(L10n t, String gateway) => gateway == 'stripe' ? t.checkoutPayStripe : t.checkoutPayRazorpay;

Future<bool> _open(String gateway, Map<String, dynamic> client, {required String title}) => switch (gateway) {
  'razorpay' => _razorpay(client, title), // India pack
  'stripe' => _stripe(client, title), // UAE, US and Germany packs
  _ => Future.value(false),
};

Future<bool> _razorpay(Map<String, dynamic> client, String title) {
  final done = Completer<bool>();
  final razorpay = Razorpay();
  razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, (_) => done.complete(true));
  razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, (_) => done.complete(false));
  razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, (_) => done.complete(true));
  razorpay.open({'key': client['key_id'], 'order_id': client['order_id'], 'name': title});

  return done.future.whenComplete(razorpay.clear);
}

/// Stripe PaymentSheet on the server's PaymentIntent (`client_secret`, `publishable_key`).
Future<bool> _stripe(Map<String, dynamic> client, String title) async {
  try {
    Stripe.publishableKey = client['publishable_key'] as String;
    await Stripe.instance.applySettings();
    await Stripe.instance.initPaymentSheet(
      paymentSheetParameters: SetupPaymentSheetParameters(paymentIntentClientSecret: client['client_secret'] as String, merchantDisplayName: title),
    );
    await Stripe.instance.presentPaymentSheet();
    return true;
  } on StripeException {
    return false; // dismissed or declined
  }
}
