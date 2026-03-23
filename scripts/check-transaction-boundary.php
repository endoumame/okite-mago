#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * okite-ai スキル: aggregate-transaction-boundary
 *
 * 1トランザクション = 1集約 ルールの違反を検出する。
 * ユースケース / コマンドハンドラ内で複数のリポジトリを呼び出している箇所を警告する。
 *
 * 使用方法: php scripts/check-transaction-boundary.php src/
 */

$targetDir = $argv[1] ?? 'src';

if (!is_dir($targetDir)) {
    echo "ディレクトリが見つかりません: {$targetDir}\n";
    exit(1);
}

$violations = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $filePath = $file->getPathname();
    $content = file_get_contents($filePath);

    // UseCase, CommandHandler, Service クラスを対象
    if (!preg_match('/class\s+\w*(UseCase|CommandHandler|ApplicationService|Handler)\b/', $content)) {
        continue;
    }

    // #[Transactional] または @Transactional アノテーションの検出
    if (preg_match('/(#\[Transactional\]|@Transactional)/', $content, $matches)) {
        // そのメソッド内で複数の Repository を呼び出しているか確認
        preg_match_all('/\$this->(\w*Repository)\b/', $content, $repoMatches);
        $uniqueRepos = array_unique($repoMatches[1] ?? []);
        if (count($uniqueRepos) > 1) {
            $violations[] = sprintf(
                "%s — @Transactional スコープ内で複数のリポジトリ (%s) を使用しています。"
                . "\n    1トランザクション = 1集約 ルール違反。"
                . " 結果整合性（ドメインイベント）を使用してください。",
                $filePath,
                implode(', ', $uniqueRepos),
            );
        }
    }

    // execute/handle メソッド内で複数の Repository->store/save/insert を検出
    if (preg_match_all('/->(?:store|save|insert|persist|delete|remove)\s*\(/', $content, $writeOps)) {
        if (count($writeOps[0]) > 1) {
            preg_match_all('/\$this->(\w*Repository)\b/', $content, $repoMatches);
            $uniqueRepos = array_unique($repoMatches[1] ?? []);
            if (count($uniqueRepos) > 1) {
                $violations[] = sprintf(
                    "%s — 単一メソッド内で複数のリポジトリ (%s) に書き込み操作を実行しています。"
                    . "\n    1トランザクション = 1集約 ルール違反の可能性。"
                    . " ドメインイベントによる結果整合性を検討してください。",
                    $filePath,
                    implode(', ', $uniqueRepos),
                );
            }
        }
    }
}

if (empty($violations)) {
    echo "✅ トランザクション境界違反は見つかりませんでした。\n";
    exit(0);
}

echo "❌ トランザクション境界違反が見つかりました:\n\n";
foreach ($violations as $v) {
    echo "  • {$v}\n\n";
}
echo "違反数: " . count($violations) . "\n";
exit(1);
