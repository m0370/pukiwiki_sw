<?php
// PukiWiki - Yet another WikiWikiWeb clone.
// swversion.inc.php
// Copyright 2025 m0370
// License: GPL v2 or (at your option) any later version
//
// Service Worker Cache Version Manager Plugin
// Version 1.1.0 (2025-11-09)
// - Automatic sw.min.js generation with PHP-based minification
// - No external dependencies (npx/terser not required)
// - Shows minification statistics (file size reduction)

function plugin_swversion_action()
{
	global $post;

	// 管理者のみ実行可能（読み取り専用モードでは実行不可）
	if (PKWK_READONLY) {
		die_message('Prohibited by admin (READONLY mode)');
	}

	// Service Workerファイルのパス
	$sw_file = 'sw.js';
	$sw_min_file = 'sw.min.js';

	// ファイルが存在しない場合
	if (!file_exists($sw_file)) {
		return array(
			'msg' => 'Error',
			'body' => '<p>Service Worker file not found: ' . htmlsc($sw_file) . '</p>'
		);
	}

	// POSTリクエスト（更新実行）
	if (isset($post['update']) && $post['update'] === 'yes') {
		return plugin_swversion_update($sw_file, $sw_min_file);
	}

	// GETリクエスト（フォーム表示）
	return plugin_swversion_show_form($sw_file);
}

// Service Workerバージョン更新を実行
function plugin_swversion_update($sw_file, $sw_min_file)
{
	// ファイル読み込み
	$content = file_get_contents($sw_file);
	if ($content === false) {
		return array(
			'msg' => 'Error',
			'body' => '<p>Failed to read Service Worker file.</p>'
		);
	}

	// 現在のバージョンを取得
	if (preg_match("/const CACHE_VERSION = '([^']+)';/", $content, $matches)) {
		$old_version = $matches[1];
	} else {
		return array(
			'msg' => 'Error',
			'body' => '<p>CACHE_VERSION not found in Service Worker file.</p>'
		);
	}

	// 新しいバージョンを生成（YYYYMMdd-HHiiss形式）
	$new_version = date('Ymd-His');

	// バージョンを置換
	$new_content = preg_replace(
		"/const CACHE_VERSION = '[^']+';/",
		"const CACHE_VERSION = '{$new_version}';",
		$content
	);

	// ファイルに書き込み
	if (file_put_contents($sw_file, $new_content) === false) {
		return array(
			'msg' => 'Error',
			'body' => '<p>Failed to write Service Worker file.</p>'
		);
	}

	// sw.min.js を自動生成（PHPでminify）
	$minify_success = false;
	$minify_error = '';

	try {
		// 基本的なJavaScript minification
		$minified = $new_content;

		// コメントを削除（// と /* */ の両方）
		$minified = preg_replace('!/\*.*?\*/!s', '', $minified);  // /* */ コメント
		$minified = preg_replace('!//[^\n]*!', '', $minified);    // // コメント

		// 複数の空白を1つに
		$minified = preg_replace('/\s+/', ' ', $minified);

		// 演算子周りの不要な空白を削除
		$minified = preg_replace('/\s*([=+\-*\/%<>!&|,;:(){}[\]])\s*/', '$1', $minified);

		// 行頭・行末の空白を削除
		$minified = trim($minified);

		// ファイルに書き込み
		if (file_put_contents($sw_min_file, $minified) !== false) {
			$minify_success = true;
		} else {
			$minify_error = 'Failed to write minified file';
		}
	} catch (Exception $e) {
		$minify_error = $e->getMessage();
	}

	// 成功メッセージ
	$minify_message = '';
	if ($minify_success) {
		$original_size = filesize($sw_file);
		$minified_size = filesize($sw_min_file);
		$reduction = round((1 - $minified_size / $original_size) * 100, 1);
		$minify_message = '<p><strong>Minified file:</strong> <code style="background: #fff; padding: 2px 6px; border-radius: 3px;">' . htmlsc($sw_min_file) . '</code> ✓ Generated successfully</p>';
		$minify_message .= '<p>Size: ' . $original_size . ' bytes → ' . $minified_size . ' bytes (' . $reduction . '% reduction)</p>';
	} else {
		$minify_message = '<p style="color: #ff6f00;"><strong>Warning:</strong> Failed to generate minified file.</p>';
		if (!empty($minify_error)) {
			$minify_message .= '<p>Error: ' . htmlsc($minify_error) . '</p>';
		}
	}

	return array(
		'msg' => 'Service Worker Version Updated',
		'body' => <<<EOD
<div style="padding: 20px; background: #e8f5e9; border-left: 4px solid #4caf50;">
	<h3 style="color: #2e7d32; margin-top: 0;">✓ Cache version updated successfully</h3>
	<p><strong>Old version:</strong> <code style="background: #fff; padding: 2px 6px; border-radius: 3px;">{$old_version}</code></p>
	<p><strong>New version:</strong> <code style="background: #fff; padding: 2px 6px; border-radius: 3px;">{$new_version}</code></p>
	{$minify_message}
	<p style="margin-bottom: 0;">All visitors will automatically receive the updated cache on their next visit.</p>
</div>
<hr>
<p><a href="?plugin=swversion">← Back to Service Worker Version Manager</a></p>
EOD
	);
}

// フォームを表示
function plugin_swversion_show_form($sw_file)
{
	// ファイル読み込み
	$content = file_get_contents($sw_file);
	if ($content === false) {
		return array(
			'msg' => 'Error',
			'body' => '<p>Failed to read Service Worker file.</p>'
		);
	}

	// 現在のバージョンを取得
	$current_version = 'Not found';
	if (preg_match("/const CACHE_VERSION = '([^']+)';/", $content, $matches)) {
		$current_version = htmlsc($matches[1]);
	}

	// キャッシュ対象ファイル一覧を取得
	$cache_files = array();
	if (preg_match("/const STATIC_CACHE_URLS = \[(.*?)\];/s", $content, $matches)) {
		preg_match_all("/'([^']+)'/", $matches[1], $file_matches);
		$cache_files = $file_matches[1];
	}

	$cache_list = '';
	if (!empty($cache_files)) {
		$cache_list = '<ul>';
		foreach ($cache_files as $file) {
			$cache_list .= '<li><code>' . htmlsc($file) . '</code></li>';
		}
		$cache_list .= '</ul>';
	}

	// 次回のバージョン番号を表示
	$next_version = date('Ymd-His');

	$self = get_base_uri();

	// 変数をエスケープ
	$sw_file_escaped = htmlsc($sw_file);
	$current_version_escaped = $current_version;  // Already escaped above
	$next_version_escaped = htmlsc($next_version);

	$body = '<div style="max-width: 800px;">';
	$body .= '<h2>Current Status</h2>';
	$body .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
	$body .= '<tr style="background: #f5f5f5;">';
	$body .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Service Worker File</th>';
	$body .= '<td style="padding: 10px; border: 1px solid #ddd;"><code>' . $sw_file_escaped . '</code></td>';
	$body .= '</tr>';
	$body .= '<tr>';
	$body .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Current Cache Version</th>';
	$body .= '<td style="padding: 10px; border: 1px solid #ddd;"><strong><code>' . $current_version_escaped . '</code></strong></td>';
	$body .= '</tr>';
	$body .= '<tr style="background: #f5f5f5;">';
	$body .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Next Version (if updated now)</th>';
	$body .= '<td style="padding: 10px; border: 1px solid #ddd;"><code>' . $next_version_escaped . '</code></td>';
	$body .= '</tr>';
	$body .= '</table>';

	$body .= '<h2>Cached Files</h2>';
	$body .= '<div style="background: #fafafa; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">';
	$body .= $cache_list;
	$body .= '</div>';

	$body .= '<h2>Update Cache Version</h2>';
	$body .= '<div style="background: #fff3e0; padding: 15px; border-left: 4px solid #ff9800; margin-bottom: 20px;">';
	$body .= '<p><strong>⚠ When to update:</strong></p>';
	$body .= '<ul>';
	$body .= '<li>After updating CSS files</li>';
	$body .= '<li>After updating JavaScript files</li>';
	$body .= '<li>After changing cached images</li>';
	$body .= '<li>After modifying the STATIC_CACHE_URLS list</li>';
	$body .= '</ul>';
	$body .= '<p style="margin-bottom: 0;">Updating the version will force all visitors to download fresh copies of cached resources on their next visit.</p>';
	$body .= '</div>';

	$body .= '<form action="' . $self . '" method="post" onsubmit="return confirm(\'Are you sure you want to update the Service Worker cache version?\');">';
	$body .= '<input type="hidden" name="plugin" value="swversion" />';
	$body .= '<input type="hidden" name="update" value="yes" />';
	$body .= '<input type="submit" value="Update Cache Version Now" style="padding: 12px 24px; font-size: 16px; background: #2196f3; color: white; border: none; border-radius: 4px; cursor: pointer;" />';
	$body .= '</form>';
	$body .= '</div>';

	$body .= '<style>';
	$body .= 'code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: \'Courier New\', monospace; font-size: 14px; }';
	$body .= '</style>';

	return array(
		'msg' => 'Service Worker Version Manager',
		'body' => $body
	);
}
