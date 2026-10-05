// Builds lib/l10n/app_<locale>.arb from the website's lang/*/site.php (exported to tool/web_strings.json),
// so the app and the website share one set of translations. Re-export and re-run after changing site.php:
//   docker compose exec -T app php -r '…' > customer_app/tool/web_strings.json   (see docs in README)
//   node tool/gen_arb.js
const fs = require('fs');
const path = require('path');

const src = require('./web_strings.json');
const out = path.join(__dirname, '..', 'lib', 'l10n');

// App-only strings (not on the website).
const extra = {
  en: { draftExpired: "This booking draft has expired. Please start a new booking.", walletTooLow: "Your wallet balance is too low for this payment.", errorNetwork: "Can't reach the server. Check your connection.", retry: 'Try again', paymentNotCompleted: 'The payment was not completed.', invoiceSaved: 'Invoice saved to {path}', signInToContinue: 'Sign in to continue', selectDate: 'Select date and time', statusExpired: 'Expired', bookAgain: 'Book again' },
  ar: { draftExpired: "انتهت صلاحية مسودة الحجز هذه. يرجى بدء حجز جديد.", walletTooLow: "رصيد محفظتك غير كافٍ لهذه الدفعة.", errorNetwork: 'تعذر الوصول إلى الخادم. تحقق من اتصالك.', retry: 'حاول مرة أخرى', paymentNotCompleted: 'لم تكتمل عملية الدفع.', invoiceSaved: 'تم حفظ الفاتورة في {path}', signInToContinue: 'سجّل الدخول للمتابعة', selectDate: 'اختر التاريخ والوقت', statusExpired: 'منتهي', bookAgain: 'احجز مرة أخرى' },
  hi: { draftExpired: "इस बुकिंग ड्राफ़्ट की समय-सीमा समाप्त हो गई है। कृपया नई बुकिंग शुरू करें।", walletTooLow: "इस भुगतान के लिए आपके वॉलेट में पर्याप्त शेष राशि नहीं है।", errorNetwork: 'सर्वर तक नहीं पहुँच सके। अपना कनेक्शन जाँचें।', retry: 'फिर से प्रयास करें', paymentNotCompleted: 'भुगतान पूरा नहीं हुआ।', invoiceSaved: 'इनवॉइस {path} में सहेजा गया', signInToContinue: 'जारी रखने के लिए साइन इन करें', selectDate: 'तारीख और समय चुनें', statusExpired: 'समाप्त', bookAgain: 'फिर से बुक करें' },
  es: { draftExpired: "Este borrador de reserva ha caducado. Inicia una nueva reserva.", walletTooLow: "El saldo de tu monedero no es suficiente para este pago.", errorNetwork: 'No se puede conectar con el servidor. Revisa tu conexión.', retry: 'Reintentar', paymentNotCompleted: 'El pago no se completó.', invoiceSaved: 'Factura guardada en {path}', signInToContinue: 'Inicia sesión para continuar', selectDate: 'Elige fecha y hora', statusExpired: 'Caducada', bookAgain: 'Reservar de nuevo' },
  de: { draftExpired: "Dieser Buchungsentwurf ist abgelaufen. Bitte starten Sie eine neue Buchung.", walletTooLow: "Ihr Wallet-Guthaben reicht für diese Zahlung nicht aus.", errorNetwork: 'Server nicht erreichbar. Bitte Verbindung prüfen.', retry: 'Erneut versuchen', paymentNotCompleted: 'Die Zahlung wurde nicht abgeschlossen.', invoiceSaved: 'Rechnung gespeichert unter {path}', signInToContinue: 'Zum Fortfahren anmelden', selectDate: 'Datum und Uhrzeit wählen', statusExpired: 'Abgelaufen', bookAgain: 'Erneut buchen' },
};

const camel = (dotted) => dotted.split(/[._]/).map((w, i) => (i ? w[0].toUpperCase() + w.slice(1) : w)).join('');

function icu(value) {
  const parts = value.split('|');
  const text = (s) => s.replace(/:(\w+)/g, '{$1}');
  if (parts.length === 2) { // "…:n seat|:n seats" → ICU plural on n
    return `{n, plural, =1{${text(parts[0])}} other{${text(parts[1])}}}`;
  }
  return text(value);
}

fs.mkdirSync(out, { recursive: true });
for (const [locale, groups] of Object.entries(src)) {
  const arb = { '@@locale': locale };
  for (const [key, value] of Object.entries(groups.site)) {
    const name = camel(key);
    arb[name] = icu(value);
    const placeholders = [...new Set([...arb[name].matchAll(/\{(\w+)\}/g)].map((m) => m[1]))].filter((p) => p !== 'n' || !arb[name].includes('plural'));
    if (locale === 'en') {
      const meta = {};
      if (arb[name].includes('plural')) meta.n = { type: 'int' };
      placeholders.forEach((p) => (meta[p] = { type: 'String' }));
      if (Object.keys(meta).length) arb[`@${name}`] = { placeholders: meta };
    }
  }
  for (const [key, value] of Object.entries(extra[locale])) {
    arb[key] = value;
    if (locale === 'en' && value.includes('{path}')) arb[`@${key}`] = { placeholders: { path: { type: 'String' } } };
  }
  fs.writeFileSync(path.join(out, `app_${locale}.arb`), JSON.stringify(arb, null, 2) + '\n');
  console.log(locale, Object.keys(arb).filter((k) => !k.startsWith('@')).length, 'strings');
}
