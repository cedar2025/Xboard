const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('admin knowledge image upload route is scoped under the knowledge admin routes', () => {
  const route = readRepoFile('app/Http/Routes/V2/AdminRoute.php');

  assert.match(route, /prefix'\s*=>\s*'knowledge'[\s\S]*post\('\/upload-image',\s*\[KnowledgeController::class,\s*'uploadImage'\]\)/);
});

test('knowledge image upload validates image type and stores random public files', () => {
  const controller = readRepoFile('app/Http/Controllers/V2/Admin/KnowledgeController.php');

  assert.match(controller, /public function uploadImage\(Request \$request\)/);
  assert.match(controller, /'file'\s*=>\s*\[[\s\S]*'required'[\s\S]*'file'[\s\S]*'image'[\s\S]*'mimes:jpg,jpeg,png,gif,webp'[\s\S]*'max:5120'[\s\S]*\]/);
  assert.doesNotMatch(controller, /svg/);
  assert.match(controller, /Storage::disk\('public'\)/);
  assert.match(controller, /knowledge-images\/'\s*\.\s*now\(\)->format\('Y\/m'\)/);
  assert.match(controller, /Str::uuid\(\)/);
  assert.match(controller, /'url'\s*=>\s*'\/knowledge-images\/'\s*\.\s*\$publicPath/);
});

test('knowledge content normalizes legacy storage image URLs on admin and user reads', () => {
  const adminController = readRepoFile('app/Http/Controllers/V2/Admin/KnowledgeController.php');
  const userController = readRepoFile('app/Http/Controllers/V1/User/KnowledgeController.php');

  assert.match(adminController, /private function normalizeKnowledgeImageUrls\(string \$body\): string/);
  assert.match(adminController, /str_replace\('\/storage\/knowledge-images\/',\s*'\/knowledge-images\/',\s*\$body\)/);
  assert.match(adminController, /\$knowledge\['body'\]\s*=\s*\$this->normalizeKnowledgeImageUrls\(\$knowledge\['body'\]\)/);
  assert.match(adminController, /\$params\['body'\]\s*=\s*\$this->normalizeKnowledgeImageUrls\(\$params\['body'\]\)/);

  assert.match(userController, /private function normalizeKnowledgeImageUrls\(string \$body\): string/);
  assert.match(userController, /str_replace\('\/storage\/knowledge-images\/',\s*'\/knowledge-images\/',\s*\$body\)/);
  assert.match(userController, /\$knowledge\['body'\]\s*=\s*\$this->normalizeKnowledgeImageUrls\(\$knowledge\['body'\]\)/);
});

test('knowledge images have public web routes that do not depend on the storage symlink', () => {
  const webRoutes = readRepoFile('routes/web.php');

  assert.match(webRoutes, /use Illuminate\\Support\\Facades\\Storage;/);
  assert.match(webRoutes, /\$serveKnowledgeImage\s*=\s*function\s*\(string \$path\)/);
  assert.match(webRoutes, /Route::get\('\/knowledge-images\/\{path\}',\s*\$serveKnowledgeImage\)/);
  assert.match(webRoutes, /Route::get\('\/storage\/knowledge-images\/\{path\}',\s*\$serveKnowledgeImage\)/);
  assert.match(webRoutes, /where\('path',\s*'\.\*'\)/);
  assert.match(webRoutes, /Storage::disk\('public'\)/);
  assert.match(webRoutes, /\$storagePath\s*=\s*'knowledge-images\/'\s*\.\s*\$path/);
  assert.match(webRoutes, /preg_match\('[^']*jpe\?g[^']*png[^']*gif[^']*webp[^']*',\s*\$path\)/);
  assert.match(webRoutes, /Cache-Control.*max-age=31536000/);
  assert.match(webRoutes, /Content-Disposition.*inline/);
});

test('admin knowledge page enhances paste events for clipboard images', () => {
  const blade = readRepoFile('resources/views/admin.blade.php');

  assert.match(blade, /function isKnowledgeRoute\(\)/);
  assert.match(blade, /\^#\\\/\?\(\?:config\\\/\)\?knowledge/);
  assert.match(blade, /\(\?:\[\\\/\?#\]\|\$\)/);
  assert.match(blade, /document\.addEventListener\("paste"/);
  assert.match(blade, /clipboardData\.items/);
  assert.match(blade, /function compressKnowledgeImage\(file\)/);
  assert.match(blade, /KNOWLEDGE_IMAGE_MAX_SIDE\s*=\s*2560/);
  assert.match(blade, /KNOWLEDGE_IMAGE_WEBP_QUALITY\s*=\s*0\.8[0-9]/);
  assert.match(blade, /file\.type\s*===\s*"image\/gif"/);
  assert.match(blade, /createImageBitmap\(file\)/);
  assert.match(blade, /canvas\.toBlob\(resolve,\s*mimeType,\s*quality\)/);
  assert.match(blade, /imageSmoothingQuality\s*=\s*"high"/);
  assert.match(blade, /"image\/webp"/);
  assert.match(blade, /blob\.size\s*>=\s*file\.size/);
  assert.match(blade, /new File\(\[blob\]/);
  assert.match(blade, /compressKnowledgeImage\(file\)\.then\(function \(uploadFile\)/);
  assert.match(blade, /new FormData\(\)/);
  assert.match(blade, /formData\.append\("file",\s*uploadFile\)/);
  assert.match(blade, /\/knowledge\/upload-image/);
  assert.match(blade, /XBOARD_ACCESS_TOKEN/);
  assert.match(blade, /authorization/);
  assert.match(blade, /access_token/);
  assert.match(blade, /JSON\.parse\(token\)/);
  assert.match(blade, /parsedToken\.value/);
  assert.match(blade, /!\[\$\{altText\}\]\(\$\{url\}\)/);
  assert.match(blade, /setRangeText/);
  assert.match(blade, /execCommand\("insertText"/);
});
