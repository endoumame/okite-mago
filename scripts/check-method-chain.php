#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * okite-ai スキル: law-of-demeter（デメテルの法則）
 *
 * メソッドチェーン（トレインレック）を検出する。
 * 3段以上のメソッドチェーンを警告する。
 *
 * 例外:
 * - Fluent API / Builder パターン（同一オブジェクトを返す）
 * - DTO / Value Object（純粋なデータ構造）
 * - Stream / Collection チェーン
 * - QueryBuilder
 *
 * 使用方法: php scripts/check-method-chain.php src/Domain/
 */

$targetDir = $argv[1] ?? 'src/Domain';
$maxChainLength = (int) ($argv[2] ?? 2);

if (!is_dir($targetDir)) {
    echo "ディレクトリが見つかりません: {$targetDir}\n";
    exit(1);
}

// 除外パターン（Fluent API 等）
$excludePatterns = [
    '/->where\(/',
    '/->select\(/',
    '/->from\(/',
    '/->join\(/',
    '/->orderBy\(/',
    '/->groupBy\(/',
    '/->having\(/',
    '/->limit\(/',
    '/->offset\(/',
    '/->build\(/',
    '/->with\w+\(/',     // Builder パターン
    '/->add\w+\(/',      // Builder パターン
    '/->set\w+\(/',      // Builder パターン
    '/->map\(/',         // Collection
    '/->filter\(/',      // Collection
    '/->reduce\(/',      // Collection
    '/->each\(/',        // Collection
    '/->collect\(/',     // Collection
    '/->pipe\(/',        // Pipeline
    '/->then\(/',        // Promise
    '/->andThen\(/',     // Result monad
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
    $lines = file($filePath);

    foreach ($lines as $lineNum => $line) {
        $trimmed = trim($line);

        // -> の数をカウント
        $arrowCount = substr_count($trimmed, '->');
        if ($arrowCount <= $maxChainLength) {
            continue;
        }

        // 除外パターンに該当するか確認
        $isExcluded = false;
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                $isExcluded = true;
                break;
            }
        }
        if ($isExcluded) {
            continue;
        }

        // $this-> から始まるチェーンは $this のメソッド呼び出しなので除外
        if (preg_match('/^\$this->/', $trimmed)) {
            // $this-> の後のチェーンをカウント
            $afterThis = preg_replace('/^\$this->/', '', $trimmed);
            $chainAfterThis = substr_count($afterThis, '->');
            if ($chainAfterThis <= $maxChainLength) {
                continue;
            }
        }

        $violations[] = sprintf(
            "%s:%d — %d段のメソッドチェーンを検出（閾値: %d）。"
            . "\n    デメテルの法則違反の可能性。"
            . " 委譲メソッドの追加、またはロジックの移動を検討してください。"
            . "\n    行: %s",
            $filePath,
            $lineNum + 1,
            $arrowCount,
            $maxChainLength,
            trim($trimmed),
        );
    }
}

if (empty($violations)) {
    echo "✅ デメテルの法則違反は見つかりませんでした。\n";
    exit(0);
}

echo "❌ デメテルの法則違反が見つかりました:\n\n";
foreach ($violations as $v) {
    echo "  • {$v}\n\n";
}
echo "違反数: " . count($violations) . "\n";
exit(1);
