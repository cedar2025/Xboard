#!/bin/bash

# 下载 sing-box rule set 文件
# 这些文件是 VPN 服务运行所必需的

ASSETS_DIR="android/app/src/main/assets"
mkdir -p "$ASSETS_DIR"

echo "Downloading geoip-cn.srs..."
curl -L -o "$ASSETS_DIR/geoip-cn.srs" \
  "https://github.com/SagerNet/sing-geoip/releases/latest/download/geoip-cn.srs"

echo "Downloading geosite-cn.srs..."  
curl -L -o "$ASSETS_DIR/geosite-cn.srs" \
  "https://github.com/SagerNet/sing-geosite/releases/latest/download/geosite-cn.srs"

echo "Checking downloaded files..."
ls -lh "$ASSETS_DIR"/*.srs

echo "Done! Assets are ready."
