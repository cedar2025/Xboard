import Cocoa
import FlutterMacOS

class MainFlutterWindow: NSWindow {
  override func awakeFromNib() {
    let flutterViewController = FlutterViewController()
    let windowFrame = self.frame
    self.contentViewController = flutterViewController
    self.setFrame(windowFrame, display: true)

    // 设置窗口尺寸
    self.setContentSize(NSSize(width: 1000, height: 700))
    self.minSize = NSSize(width: 800, height: 600)
    self.center()
    self.title = "ElephantRoute"

    // 窗口样式
    self.titlebarAppearsTransparent = false
    self.isMovableByWindowBackground = true
    self.styleMask.insert(.fullSizeContentView)

    RegisterGeneratedPlugins(registry: flutterViewController)

    super.awakeFromNib()
  }
}
