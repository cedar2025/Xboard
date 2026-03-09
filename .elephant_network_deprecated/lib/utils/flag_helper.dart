/// 根据节点名称返回对应的国旗 emoji
/// 供首页和节点列表页共用
String getFlagEmoji(String name) {
  final lowerName = name.toLowerCase();

  if (lowerName.contains('香港') || lowerName.contains('hong kong') || lowerName.contains('hk')) return '🇭🇰';
  if (lowerName.contains('台湾') || lowerName.contains('taiwan') || lowerName.contains('tw') || lowerName.contains('新北') || lowerName.contains('彰化') || lowerName.contains('中华电信')) return '🇹🇼';
  if (lowerName.contains('美国') || lowerName.contains('united states') || lowerName.contains('us') || lowerName.contains('硅谷') || lowerName.contains('圣何塞') || lowerName.contains('洛杉矶') || lowerName.contains('西雅图') || lowerName.contains('纽约') || lowerName.contains('波特兰')) return '🇺🇸';
  if (lowerName.contains('日本') || lowerName.contains('japan') || lowerName.contains('jp') || lowerName.contains('东京') || lowerName.contains('大阪') || lowerName.contains('埼玉')) return '🇯🇵';
  if (lowerName.contains('新加坡') || lowerName.contains('singapore') || lowerName.contains('sg') || lowerName.contains('狮城')) return '🇸🇬';
  if (lowerName.contains('韩国') || lowerName.contains('korea') || lowerName.contains('kr') || lowerName.contains('首尔') || lowerName.contains('春川')) return '🇰🇷';
  if (lowerName.contains('英国') || lowerName.contains('united kingdom') || lowerName.contains('uk') || lowerName.contains('伦敦')) return '🇬🇧';
  if (lowerName.contains('德国') || lowerName.contains('germany') || lowerName.contains('de') || lowerName.contains('法兰克福')) return '🇩🇪';
  if (lowerName.contains('法国') || lowerName.contains('france') || lowerName.contains('fr') || lowerName.contains('巴黎')) return '🇫🇷';
  if (lowerName.contains('俄罗斯') || lowerName.contains('russia') || lowerName.contains('ru') || lowerName.contains('莫斯科') || lowerName.contains('伯力')) return '🇷🇺';
  if (lowerName.contains('加拿大') || lowerName.contains('canada') || lowerName.contains('ca') || lowerName.contains('多伦多') || lowerName.contains('温哥华') || lowerName.contains('蒙特利尔')) return '🇨🇦';
  if (lowerName.contains('澳大利亚') || lowerName.contains('australia') || lowerName.contains('au') || lowerName.contains('悉尼') || lowerName.contains('墨尔本')) return '🇦🇺';
  if (lowerName.contains('印度') || lowerName.contains('india') || lowerName.contains('in') || lowerName.contains('孟买')) return '🇮🇳';

  return '🏳️';
}
