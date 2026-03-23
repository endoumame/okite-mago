#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * okite-ai スキル: repository-design
 *
 * リポジトリの命名規約と設計ルールを検証する。
 *
 * ルール:
 * 1. リポジトリ名は AggregateNameRepository パターンに従うこと
 * 2. CQS（コマンド・クエリ分離）: store は void を返す、find は集約を返す
 * 3. リポジトリ内で他のリポジトリを呼び出さないこと
 * 4. 許可されるメソッド名: store, findById, findBy*, delete, storeMulti, deleteMulti, exists*
 * 5. ドメインロジック（leave, activate, cancel 等）をリポジトリに書かないこと
 *
 * 使用方法: php scripts/check-repository-naming.php src/
 */

$targetDir = $argv[1] ?? 'src';

if (!is_dir($targetDir)) {
    echo "ディレクトリが見つかりません: {$targetDir}\n";
    exit(1);
}

// 許可されるメソッド名パターン
$allowedMethodPatterns = [
    '/^store$/',
    '/^storeMulti$/',
    '/^findById$/',
    '/^findBy[A-Z]/',
    '/^findAll$/',
    '/^delete$/',
    '/^deleteMulti$/',
    '/^deleteBy[A-Z]/',
    '/^exists$/',
    '/^existsBy[A-Z]/',
    '/^count$/',
    '/^countBy[A-Z]/',
    '/^nextIdentity$/',
];

// 禁止されるメソッド名パターン（ドメインロジック）
$forbiddenMethodPatterns = [
    '/^(activate|deactivate|enable|disable|cancel|approve|reject|publish|unpublish)$/',
    '/^(leave|join|enter|exit|start|stop|pause|resume|complete|fail)$/',
    '/^(update|modify|change|set|assign|unassign|attach|detach)$/',
    '/^(calculate|compute|process|validate|verify|check)$/',
];

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

    // Repository クラス/インターフェースを検出
    if (!preg_match('/(?:class|interface)\s+(\w*Repository)\b/', $content, $classMatch)) {
        continue;
    }

    $className = $classMatch[1];

    // ルール 1: テーブル名や DTO 名ベースの命名を検出
    if (preg_match('/(Table|Dto|Record|Row|Entity|Model)Repository$/', $className)) {
        $violations[] = sprintf(
            "%s — リポジトリ名 '%s' が集約名ベースではありません。"
            . "\n    AggregateNameRepository パターン（例: OrderRepository）に変更してください。",
            $filePath,
            $className,
        );
    }

    // ルール 3: 他のリポジトリへの依存を検出
    preg_match_all('/(?:private|protected|public)\s+(?:readonly\s+)?(\w*Repository)\s/', $content, $depMatches);
    foreach ($depMatches[1] as $dep) {
        if ($dep !== $className) {
            $violations[] = sprintf(
                "%s — リポジトリ '%s' が他のリポジトリ '%s' に依存しています。"
                . "\n    リポジトリ間の呼び出しは禁止。"
                . " 集約間の連携はアプリケーションサービス層で行ってください。",
                $filePath,
                $className,
                $dep,
            );
        }
    }

    // ルール 4 & 5: メソッド名の検証
    preg_match_all('/public\s+function\s+(\w+)\s*\(/', $content, $methodMatches);
    foreach ($methodMatches[1] as $method) {
        if ($method === '__construct') {
            continue;
        }

        // 禁止パターンのチェック
        foreach ($forbiddenMethodPatterns as $pattern) {
            if (preg_match($pattern, $method)) {
                $violations[] = sprintf(
                    "%s — リポジトリ '%s' にドメインロジックメソッド '%s()' があります。"
                    . "\n    リポジトリは永続化の責務のみ。"
                    . " このロジックは集約またはドメインサービスに移動してください。",
                    $filePath,
                    $className,
                    $method,
                );
                break;
            }
        }

        // 許可パターンのチェック
        $isAllowed = false;
        foreach ($allowedMethodPatterns as $pattern) {
            if (preg_match($pattern, $method)) {
                $isAllowed = true;
                break;
            }
        }
        if (!$isAllowed && !preg_match('/^__/', $method)) {
            $violations[] = sprintf(
                "%s — リポジトリ '%s' に非標準メソッド '%s()' があります。"
                . "\n    許可されるメソッド: store, findById, findBy*, delete, exists*"
                . "\n    ドメインロジックが含まれている場合は集約に移動してください。",
                $filePath,
                $className,
                $method,
            );
        }
    }
}

if (empty($violations)) {
    echo "✅ リポジトリ設計違反は見つかりませんでした。\n";
    exit(0);
}

echo "❌ リポジトリ設計違反が見つかりました:\n\n";
foreach ($violations as $v) {
    echo "  • {$v}\n\n";
}
echo "違反数: " . count($violations) . "\n";
exit(1);
