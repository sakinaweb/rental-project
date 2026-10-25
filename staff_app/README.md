# Staff app (Flutter)

The staff app from MD §7.10: a branch's pickups and returns, and the handover and return checklists. It talks only to the `Staff` endpoints of the REST API documented in [`../docs/openapi.json`](../docs/openapi.json).

Owner decisions (Phase 10):

- Staff sign in with a **staff** account; the admin assigns each staff user to a **branch** (Users page). The app shows that branch's pickups due today (late ones included) and every vehicle that is out.
- **Handover** and **return** each need at least **4 photos** (front, back, left, right; up to 12), the **odometer** and the **fuel level**; notes are optional. The return odometer can't be lower than at handover.
- **Damage** found at return is recorded with an estimated cost and photos as *reported*; the admin approves or waives it in the panel before anything is charged.

Stack: Flutter 3.x, Riverpod, dio, go_router, image_picker (camera, photos resized to 1600 px), flutter_secure_storage, intl, flutter_localizations + ARB (English; other locales can be added in `lib/l10n`).

```
lib/
  core/       api client + models, session, router, theme/ui helpers, config
  features/   login, today (pickups/returns), booking, checklist (handover/return + damages)
  l10n/       app_en.arb
```

## Run against the local Docker backend

```sh
# backend: docker compose up -d   (from the repo root); once: docker compose exec app php artisan storage:link
flutter run                                   # Android emulator: API at http://10.0.2.2:8000/api/v1
flutter run --dart-define=API_BASE_URL=https://api.example.com/api/v1
```

Photos are stored on the backend's `INSPECTIONS_DISK` (public by default, S3 optional).

## Checks

```sh
flutter analyze
flutter test          # real API client against a fake backend: staff-only sign-in, day list, handover and return uploads
flutter build apk --debug
```
