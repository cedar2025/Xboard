import 'vpn_state.dart';

/// VPN 管理器抽象接口
abstract class VpnManager {
  /// 请求 VPN 权限
  /// 返回 true 表示已授权,false 表示用户拒绝
  Future<bool> requestPermission();

  /// 启动 VPN
  /// [config] sing-box JSON 配置
  Future<void> start(String config);

  /// 停止 VPN
  Future<void> stop();

  /// VPN 状态流
  Stream<VpnState> get stateStream;

  /// 当前 VPN 状态
  VpnState get currentState;

  /// 测试节点延迟 (UrlTest)
  /// [groupTag] 节点组标签/节点名称
  /// 返回延迟毫秒数, -1 表示超时/失败
  Future<int> urlTest(String groupTag);

  /// 切换出站节点 (用于热切换)
  /// [groupTag] 组标签 (如 "proxy")
  /// [outboundTag] 选中的节点标签
  Future<void> selectOutbound(String groupTag, String outboundTag);

  /// 释放资源
  void dispose();
}
