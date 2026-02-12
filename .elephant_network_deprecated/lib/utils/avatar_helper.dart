
class AvatarHelper {
  // Base URL for DiceBear avatars
  // styles: adventurer, adventurer-neutral, avataaars, big-ears, big-ears-neutral, big-smile, bottts, croodles, croodles-neutral, fun-emoji, icons, identicon, initials, lorelei, lorelei-neutral, micah, miniavs, open-peeps, personas, pixel-art, pixel-art-neutral, shapes, thumbs
  // We use 'adventurer' for a fun, cartoon look as requested.
  static const String _baseUrl = 'https://api.dicebear.com/7.x/adventurer/png';

  // Generate a URL based on a seed
  static String getAvatarUrl(String seed) {
    return '$_baseUrl?seed=$seed&backgroundColor=b6e3f4,c0aede,d1d4f9';
  }

  // 20 Preset seeds for the selection screen
  static final List<String> presetSeeds = List.generate(
    20,
    (index) => 'preset_avatar_seed_$index',
  );
}
