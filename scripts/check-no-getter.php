#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * okite-ai スキル: breach-encapsulation-naming, tell-dont-ask
 *
 * ドメインモデル（Entity）で通常の getter（get* プレフィックス）を検出し警告する。
 * 許容されるのは breachEncapsulationOf* プレフィックスのみ。
 * 値オブジェクト（ValueObject, VO）は通常のアクセサを許容する。
 *
 * 使用方法: php scripts/check-no-getter.php src/Domain/
 */

$targetDir = $argv[1] ?? 'src/Domain';

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

    // 値オブジェクトはスキップ（クラス名に ValueObject, VO が含まれるか Immutable 系）
    if (preg_match('/class\s+\w*(ValueObject|VO)\b/i', $content)) {
        continue;
    }

    // Entity / Aggregate クラスでの getter 検出
    $lines = explode("\n", $content);
    foreach ($lines as $lineNum => $line) {
        // public function get* を検出（breachEncapsulationOf は除外）
        if (preg_match('/\bpublic\s+function\s+(get[A-Z]\w*)\s*\(/', $line, $matches)) {
            $methodName = $matches[1];
            // getId は許容（エンティティの識別子）
            if ($methodName === 'getId' || $methodName === 'getIterator') {
                continue;
            }
            $violations[] = sprintf(
                "%s:%d — getter '%s()' を検出。Tell Don't Ask 原則違反の可能性。"
                . " breachEncapsulationOf%s() に変更するか、振る舞いメソッドに置き換えてください。",
                $filePath,
                $lineNum + 1,
                $methodName,
                substr($methodName, 3),
            );
        }
    }

    // breachEncapsulationOf + if パターンの検出（誤用）
    if (preg_match_all('/breachEncapsulationOf\w+\(\)/', $content, $breachMatches)) {
        foreach ($breachMatches[0] as $breachCall) {
            // breachEncapsulationOf の戻り値を条件分岐で使っている箇所を検出
            if (preg_match('/if\s*\(.*' . preg_quote($breachCall, '/') . '/', $content)) {
                $violations[] = sprintf(
                    "%s — '%s' の戻り値を条件分岐で使用しています。"
                    . " Tell Don't Ask 原則に従い、判定ロジックをオブジェクト内部に移動してください。",
                    $filePath,
                    $breachCall,
                );
            }
        }
    }
}

if (empty($violations)) {
    echo "✅ getter 命名規約違反は見つかりませんでした。\n";
    exit(0);
}

echo "❌ getter 命名規約違反が見つかりました:\n\n";
foreach ($violations as $v) {
    echo "  • {$v}\n";
}
echo "\n違反数: " . count($violations) . "\n";
exit(1);
