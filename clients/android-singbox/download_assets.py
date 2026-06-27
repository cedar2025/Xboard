
import os
import urllib.request
import ssl

# Disable SSL verification for simplicity (avoid cert errors)
ssl._create_default_https_context = ssl._create_unverified_context

ASSET_DIR = "app/src/main/assets/srs"
BASE_URL_GEOSITE = "https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geosite"
BASE_URL_GEOIP = "https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/sing/geo/geoip"

FILES = {
    "geosite-telegram.srs": f"{BASE_URL_GEOSITE}/telegram.srs",
    "geoip-telegram.srs": f"{BASE_URL_GEOIP}/telegram.srs",
    "geosite-google.srs": f"{BASE_URL_GEOSITE}/google.srs",
    "geoip-google.srs": f"{BASE_URL_GEOIP}/google.srs",
    "geosite-youtube.srs": f"{BASE_URL_GEOSITE}/youtube.srs",
    "geosite-netflix.srs": f"{BASE_URL_GEOSITE}/netflix.srs",
    "geoip-netflix.srs": f"{BASE_URL_GEOIP}/netflix.srs",
    "geosite-openai.srs": f"{BASE_URL_GEOSITE}/openai.srs",
    "geosite-apple.srs": f"{BASE_URL_GEOSITE}/apple.srs",
    "geosite-microsoft.srs": f"{BASE_URL_GEOSITE}/microsoft.srs",
    "geosite-github.srs": f"{BASE_URL_GEOSITE}/github.srs",
    "geosite-geolocation-!cn.srs": f"{BASE_URL_GEOSITE}/geolocation-!cn.srs",
    "geosite-category-ads-all.srs": f"{BASE_URL_GEOSITE}/category-ads-all.srs",
    "geosite-cn.srs": f"{BASE_URL_GEOSITE}/cn.srs",
    "geoip-cn.srs": f"{BASE_URL_GEOIP}/cn.srs",
    "geosite-private.srs": f"{BASE_URL_GEOSITE}/private.srs",
    "geoip-private.srs": f"{BASE_URL_GEOIP}/private.srs"
}

if not os.path.exists(ASSET_DIR):
    os.makedirs(ASSET_DIR)
    print(f"Created directory: {ASSET_DIR}")

for filename, url in FILES.items():
    filepath = os.path.join(ASSET_DIR, filename)
    if os.path.exists(filepath):
        print(f"[SKIP] {filename} already exists.")
        continue
    
    print(f"[DOWNLOADING] {filename} from {url}...")
    try:
        urllib.request.urlretrieve(url, filepath)
        print(f"[SUCCESS] Downloaded {filename} ({os.path.getsize(filepath)} bytes)")
    except Exception as e:
        print(f"[ERROR] Failed to download {filename}: {e}")

print("Download process completed.")
