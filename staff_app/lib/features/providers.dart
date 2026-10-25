import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../core/api.dart';

final dayProvider = FutureProvider.autoDispose<Day>((ref) => ref.watch(apiProvider).day(DateTime.now()));
final bookingProvider = FutureProvider.autoDispose.family<StaffBooking, int>((ref, id) => ref.watch(apiProvider).booking(id));

/// The camera: a resized photo's file path, or null if cancelled. Overridden in tests.
typedef TakePhoto = Future<String?> Function();
final takePhotoProvider = Provider<TakePhoto>(
  (_) =>
      () async => (await ImagePicker().pickImage(source: ImageSource.camera, maxWidth: 1600, imageQuality: 80))?.path,
);
