<?php

return [
    'nav' => ['search' => 'Suche', 'my_bookings' => 'Meine Buchungen', 'wallet' => 'Wallet', 'profile' => 'Profil', 'login' => 'Anmelden', 'register' => 'Konto erstellen', 'logout' => 'Abmelden', 'language' => 'Sprache'],
    'common' => ['loading' => 'Wird geladen…', 'back' => 'Zurück', 'optional' => 'optional', 'times_note' => 'Alle Zeiten werden in :tz angezeigt.'],
    'search' => [
        'title' => 'Motorrad oder Auto finden', 'country' => 'Land', 'city' => 'Stadt', 'any' => 'Alle', 'pick_up' => 'Abholung', 'return' => 'Rückgabe',
        'category' => 'Kategorie', 'type' => 'Typ', 'bike' => 'Motorrad', 'car' => 'Auto', 'seats' => 'Min. Sitze', 'button' => 'Suchen',
        'no_results' => 'Keine Fahrzeuge passen zu Ihrer Suche. Versuchen Sie andere Daten oder Filter.', 'dates_invalid' => 'Die Rückgabe muss nach der Abholung liegen.',
        'total_for_dates' => 'gesamt für Ihre Daten', 'pick_dates_for_price' => 'Wählen Sie Abholung und Rückgabe, um den Preis zu sehen.',
    ],
    'vehicle' => [
        'seats' => ':n Sitz|:n Sitze', 'seats_label' => 'Sitze', 'transmission' => 'Getriebe', 'fuel' => 'Kraftstoff', 'branch' => 'Filiale',
        'available' => 'In diesem Zeitraum verfügbar', 'unavailable' => 'In diesem Zeitraum nicht verfügbar', 'addons' => 'Extras',
        'addon_per_day' => 'pro Tag', 'addon_per_booking' => 'pro Buchung', 'qty' => 'Menge', 'coupon' => 'Gutscheincode', 'quote' => 'Ihr Preis',
        'subtotal' => 'Zwischensumme', 'discounts' => 'Rabatte', 'tax' => 'Steuer', 'total' => 'Gesamt', 'deposit' => 'Erstattbare Kaution', 'due_now' => 'Jetzt zu zahlen',
        'book_now' => 'Jetzt buchen', 'login_to_book' => 'Zum Buchen anmelden', 'price_unavailable' => 'Kein Preis für diesen Zeitraum',
    ],
    'auth' => [
        'sign_in' => 'Anmelden', 'with_password' => 'Passwort', 'with_otp' => 'SMS-Code', 'email' => 'E-Mail', 'password' => 'Passwort',
        'password_confirmation' => 'Passwort bestätigen', 'name' => 'Vollständiger Name', 'phone' => 'Telefon', 'phone_hint' => 'Internationales Format, z. B. +4915100000000',
        'code' => '6-stelliger Code', 'send_code' => 'Code senden', 'verify' => 'Bestätigen und anmelden', 'no_account' => 'Neu hier?',
        'have_account' => 'Sie haben bereits ein Konto?', 'create_account' => 'Konto erstellen', 'too_many' => 'Zu viele Versuche. Bitte warten Sie eine Minute und versuchen Sie es erneut.',
    ],
    'checkout' => [
        'title' => 'Bezahlung', 'pay' => 'Bezahlen', 'pay_wallet' => 'Mit Wallet bezahlen', 'pay_stripe' => 'Mit Karte bezahlen (Stripe)', 'pay_razorpay' => 'Mit Razorpay bezahlen',
        'no_card' => 'Kartenzahlung ist derzeit nicht verfügbar.', 'hold_note' => 'Wir halten dieses Fahrzeug bis :time Uhr für Sie frei.',
    ],
    'confirmation' => [
        'title' => 'Buchungsstatus', 'confirmed' => 'Ihre Buchung ist bestätigt.', 'waiting' => 'Warten auf die Zahlungsbestätigung. Diese Seite aktualisiert sich automatisch.',
        'failed' => 'Diese Buchung wurde storniert. Erhaltenes Geld wurde Ihrem Wallet gutgeschrieben.', 'card_failed' => 'Die Kartenzahlung ist nicht durchgegangen. Sie können es erneut versuchen.',
        'view_booking' => 'Buchung ansehen',
    ],
    'booking' => [
        'title' => 'Buchung Nr. :id', 'none' => 'Sie haben noch keine Buchungen.', 'amount_due' => 'Offener Betrag', 'amount_paid' => 'Bezahlt', 'pay_balance' => 'Jetzt bezahlen',
        'new_end' => 'Neue Rückgabezeit', 'extend' => 'Buchung verlängern', 'cancel' => 'Buchung stornieren', 'cancel_confirm' => 'Diese Buchung stornieren? Alles, was Sie bezahlt haben, geht zurück in Ihr Wallet.',
        'invoice' => 'Rechnung herunterladen (PDF)', 'deposit_status' => 'Kaution', 'cancelled_notice' => 'Buchung storniert. Die Erstattung ist in Ihrem Wallet.',
        'extended_notice' => 'Buchung verlängert.',
    ],
    'deposit' => ['held' => 'einbehalten', 'released' => 'zurückgegeben'],
    'status' => [
        'draft' => 'Zahlung ausstehend', 'pending_payment' => 'Zahlung läuft', 'confirmed' => 'Bestätigt', 'picked_up' => 'In Miete', 'returned' => 'Zurückgegeben',
        'completed' => 'Abgeschlossen', 'settled' => 'Abgerechnet', 'cancelled' => 'Storniert', 'no_show' => 'Nicht erschienen',
    ],
    'wallet' => [
        'balance' => 'Guthaben', 'empty' => 'Ihr Wallet ist leer.', 'no_transactions' => 'Noch keine Buchungen im Wallet.', 'top_up' => 'Aufladen', 'amount' => 'Betrag',
        'currency' => 'Währung', 'date' => 'Datum', 'type' => 'Art', 'processing' => 'Schließen Sie die Zahlung im geöffneten Fenster ab. Ihr Guthaben wird aktualisiert, sobald der Zahlungsanbieter sie bestätigt.',
        'amount_invalid' => 'Bitte einen gültigen Betrag eingeben.', 'type_topup' => 'Aufladung', 'type_payment' => 'Buchungszahlung', 'type_refund' => 'Erstattung', 'type_deposit_release' => 'Kaution zurückgegeben',
    ],
    'profile' => ['save' => 'Speichern', 'saved' => 'Profil gespeichert.'],
];
