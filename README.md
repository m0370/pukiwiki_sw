# swversion.inc.php - PukiWiki Service Worker Cache Version Manager

PukiWikiサイトにService Workerを導入している場合に、キャッシュバージョンを簡単に管理できるプラグインです。

**Note**: このプラグインはService Workerを導入済みのPukiWikiサイト専用です。Service Workerを導入していない場合は、まず `sw.js` と `pukiwiki.skin.php` を配置してください。

## 概要

Service Workerを使ってサイトを高速化している場合、CSS/JavaScriptファイルを更新したときにキャッシュバージョンも更新する必要があります。このプラグインを使うと、ブラウザ上でワンクリックでキャッシュバージョンを更新できます。

## 解説

https://oncologynote.jp/?5dc8d8f7f6

## 機能

- **ブラウザ上で簡単更新**: 管理画面からボタン一つでキャッシュバージョンを更新
- **自動バージョン生成**: 日時ベース（`YYYYMMdd-HHiiss`形式）で自動生成
- **自動minify生成**: sw.min.js を自動的に生成（v1.1.0以降）
- **PHPベースの圧縮**: 外部依存なし（npx/terserなど不要）
- **圧縮率表示**: ファイルサイズ削減率をリアルタイム表示
- **現在の状態を確認**: 現在のキャッシュバージョンとキャッシュ対象ファイルを一覧表示
- **セキュリティ**: Basic認証とPKWK_READONLYモードでアクセス制御

## インストール方法

### 1. 必要なファイルを配置

以下の3つのファイルをPukiWikiに配置します：

```
/sw.js                          # Service Workerファイル（ルートディレクトリ）
/sw.min.js                      # Service Worker minifiedファイル（自動生成）
/skin/pukiwiki.skin.php         # Service Worker登録コード付きskinファイル
/plugin/swversion.inc.php       # このプラグイン
```

**注**: `sw.min.js` は `swversion.inc.php` でバージョン更新時に自動生成されます。手動で作成する必要はありません。

なお、**Service Worker登録コード付きskinファイル**はデフォルトのスキンに以下のscriptを追記することでも作成可能です。
`</body></html>` の直前にこれを挿入してください。

```
<!-- Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
	window.addEventListener('load', function() {
		navigator.serviceWorker.register('/sw.min.js')
			.then(function(registration) {
				console.log('Service Worker registered with scope:', registration.scope);
			})
			.catch(function(error) {
				console.log('Service Worker registration failed:', error);
			});
	});
}
</script>
```

### 2. ファイルの配置場所

- **sw.js**: PukiWikiのルートディレクトリ（index.phpと同じ場所）に配置
- **sw.min.js**: 自動生成されるため手動配置不要（初回は `?plugin=swversion` でバージョン更新を実行）
- **pukiwiki.skin.php**: `/skin/`ディレクトリに配置（既存ファイルを上書き）
- **swversion.inc.php**: `/plugin/`ディレクトリに配置

### 3. 動作確認

ブラウザで以下のURLにアクセスします（Basic認証でログインした状態で）：

```
https://your-site.com/?plugin=swversion
```

管理画面が表示されれば成功です。

## 使い方

### 1. 管理画面にアクセス

```
https://your-site.com/?plugin=swversion
```

Basic認証でログインしている必要があります。

### 2. 現在の状態を確認

管理画面には以下の情報が表示されます：

- **Cache Version**: 現在のキャッシュバージョン（例: `20251027-143052`）
- **SW File Path**: sw.jsファイルのパス
- **Cached Resources**: キャッシュされているファイルの一覧

### 3. キャッシュバージョンを更新

1. 「**Update Cache Version Now**」ボタンをクリック
2. 確認ダイアログで「OK」をクリック
3. 完了メッセージが表示されます

### 4. 更新の確認

- 新しいバージョン番号が表示されます（例: `20251027-154230`）
- この時点で訪問者のブラウザは次回アクセス時に新しいキャッシュをダウンロードします

## 運用フロー

### CSS/JavaScriptを更新した場合

1. 通常通りCSS/JavaScriptファイルを編集・アップロード
2. ブラウザで `?plugin=swversion` にアクセス
3. 「Update Cache Version Now」ボタンをクリック
4. 完了

これで訪問者のブラウザに新しいファイルが配信されます。

### デザイン変更時の推奨手順

```
1. CSSファイルを編集
2. サーバーにアップロード
3. ?plugin=swversion でキャッシュバージョン更新
4. 自分のブラウザでキャッシュクリアして確認
5. 完了
```

## セキュリティ

### アクセス制御

- **PKWK_READONLY モード**: 読み取り専用モードでは実行不可
- **Basic認証**: 管理者のみアクセス可能
- **確認ダイアログ**: 誤操作防止のため更新時に確認

### ファイル書き込み権限

sw.jsとsw.min.jsファイルに書き込み権限が必要です。パーミッションを確認してください：

```bash
chmod 644 sw.js
chmod 644 sw.min.js  # 初回は存在しないため、自動生成後に設定
```

Webサーバーのユーザー（通常は `www-data` や `apache`）が書き込める必要があります。

## トラブルシューティング

### 「Error: sw.js file not found」と表示される

**原因**: sw.jsファイルが正しい場所にありません

**解決方法**:

- sw.jsファイルがPukiWikiのルートディレクトリ（index.phpと同じ場所）にあるか確認
- パスが `/path/to/pukiwiki/sw.js` になっているか確認

### 「Failed to write to sw.js file」と表示される

**原因**: sw.jsファイルに書き込み権限がありません

**解決方法**:

```bash
chmod 644 sw.js
chown www-data:www-data sw.js  # Webサーバーのユーザーに合わせる
```

### 「Prohibited by admin (READONLY mode)」と表示される

**原因**: PukiWikiが読み取り専用モードになっています

**解決方法**:

- `pukiwiki.ini.php` で `$pkwk_readonly = 0;` に設定
- または一時的に読み取り専用モードを解除

### Service Workerが動作しない

**確認事項**:

1. **sw.jsの配置場所**: ルートディレクトリ（`/sw.js`）にあるか
2. **HTTPSか**: Service WorkerはHTTPS必須（localhostは例外）
3. **ブラウザの対応**: モダンブラウザ（Chrome, Firefox, Safari, Edge）を使用
4. **Console確認**: ブラウザのDevToolsでエラーメッセージを確認

ブラウザのDevTools (F12) → Console で以下のメッセージが出れば成功：

```
Service Worker registered with scope: https://your-site.com/
```

### キャッシュが更新されない

**訪問者側での確認**:

1. ブラウザのキャッシュをクリア（Ctrl+Shift+Delete）
2. スーパーリロード（Ctrl+Shift+R または Cmd+Shift+R）
3. DevTools → Application → Service Workers で「Update」をクリック

**管理者側での確認**:

1. `?plugin=swversion` でバージョンが更新されているか確認
2. sw.jsファイルを直接開いて `CACHE_VERSION` の値を確認

## カスタマイズ

### キャッシュ対象ファイルの追加

sw.jsファイルを編集して、`STATIC_CACHE_URLS` 配列にファイルを追加します：

```javascript
const STATIC_CACHE_URLS = [
  '/skin/pukiwiki.css',
  '/skin/main.js',
  '/image/pukiwiki.png',
  // 追加したいファイル
  '/skin/custom.css',
  '/image/logo.png'
];
```

### バージョン番号の形式変更

プラグイン内の以下の行を編集します（`swversion.inc.php` の48行目付近）：

```php
// 現在: YYYYMMdd-HHiiss 形式
$new_version = date('Ymd-His');

// 例: YYYYMMDD-vN 形式にしたい場合
$new_version = date('Ymd') . '-v' . time();
```

## ファイル構成

```
your-pukiwiki/
├── index.php
├── sw.js                       # Service Workerファイル（このプラグインが更新）
├── sw.min.js                   # Service Worker minifiedファイル（自動生成）
├── skin/
│   └── pukiwiki.skin.php       # Service Worker登録コード含む
└── plugin/
    └── swversion.inc.php       # このプラグイン
```

## 動作環境

- **PukiWiki**: 1.5.3以降
- **PHP**: 7.0以降
- **Webサーバー**: Apache, Nginx等
- **SSL/TLS**: HTTPS必須（Service Worker要件）

## ライセンス

GPL v2 or (at your option) any later version

## 作成者

m0370 (2025)

## 関連リンク

- [PukiWiki公式サイト](https://pukiwiki.osdn.jp/)
- [Service Worker API - MDN](https://developer.mozilla.org/ja/docs/Web/API/Service_Worker_API)
- [Service Worker の紹介 - Google Developers](https://developers.google.com/web/fundamentals/primers/service-workers)

## バージョン履歴

- **1.1.0** (2025-11-09): 自動minify機能追加
  - sw.min.js 自動生成機能を追加
  - PHPベースのJavaScript minification実装
  - 外部依存なし（npx/terser不要）
  - ファイルサイズ削減率表示
  - pukiwiki.skin.phpでsw.min.js使用に変更

- **1.0.0** (2025-10-27): 初回リリース
  - 基本機能実装
  - 日時ベースのバージョン自動生成
  - 管理画面UI
  - セキュリティチェック
