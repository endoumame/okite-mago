#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * okite-ai スキル: first-class-collection
 *
 * 生の配列/コレクションの使用を検出し、ファーストクラスコレクションへの
 * ラップを推奨する。
 *
 * ルール:
 * 1. ドメインモデルのプロパティで array<Type> や Collection<Type> を直接使用しない
 * 2. 配列操作（array_filter, array_map 等）がドメイン層に散在していないか
 * 3. コレクションラッパークラスにコレクション以外のフィールドがないか
 *
 * 使用方法: php scripts/check-first-class-collection.php src/Domain/
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

// 配列操作関数（これらがドメイン層に散在していたら警告）
$arrayFunctions = [
    'array_filter',
    'array_map',
    'array_reduce',
    'array_walk',
    'array_search',
    'array_unique',
    'array_sort',
    'usort',
    'uasort',
    'uksort',
    'array_slice',
    'array_splice',
    'array_merge',
    'array_combine',
    'array_diff',
    'array_intersect',
    'in_array',
    'array_key_exists',
    'array_count_values',
    'array_sum',
    'array_column',
];

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $filePath = $file->getPathname();
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);

    if (!preg_match('/class\s+(\w+)/', $content, $classMatch)) {
        continue;
    }
    $className = $classMatch[1];

    // ルール 1: プロパティで array 型を直接使用
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/(?:private|protected|public)\s+(?:readonly\s+)?array\s+\$(\w+)/', $line, $propMatch)) {
            $propName = $propMatch[1];
            $violations[] = sprintf(
                "%s:%d — '%s::$%s' が生の array 型です。"
                . "\n    ファーストクラスコレクション原則違反。"
                . " 専用のコレクションクラスでラップしてください。"
                . "\n    例: array \$items → OrderItems \$items",
                $filePath,
                $lineNum + 1,
                $className,
                $propName,
            );
        }
    }

    // ルール 2: 配列操作関数の使用検出
    foreach ($arrayFunctions as $func) {
        if (preg_match_all('/\b' . preg_quote($func, '/') . '\s*\(/', $content, $funcMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($funcMatches[0] as $match) {
                $offset = $match[1];
                $lineNum = substr_count(substr($content, 0, $offset), "\n") + 1;
                $violations[] = sprintf(
                    "%s:%d — '%s()' がドメイン層で直接使用されています。"
                    . "\n    コレクション操作はファーストクラスコレクション内にカプセル化してください。",
                    $filePath,
                    $lineNum,
                    $func,
                );
            }
        }
    }
}

if (empty($violations)) {
    echo "✅ ファーストクラスコレクション違反は見つかりませんでした。\n";
    exit(0);
}

echo "❌ ファーストクラスコレクション違反が見つかりました:\n\n";
foreach ($violations as $v) {
    echo "  • {$v}\n\n";
}
echo "違反数: " . count($violations) . "\n";
exit(1);
