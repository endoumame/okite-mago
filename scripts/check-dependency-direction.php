#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * okite-ai スキル: clean-architecture, repository-placement
 *
 * クリーンアーキテクチャの依存方向ルールを検証する。
 * Domain 層が外側の層（Infrastructure, Interface Adapters）に依存していないことを確認。
 *
 * 想定ディレクトリ構造:
 *   src/Domain/          — ドメイン層（最内側）
 *   src/UseCase/         — ユースケース層
 *   src/Application/     — アプリケーション層（UseCase の別名）
 *   src/Adapter/         — インターフェースアダプタ層
 *   src/Infrastructure/  — インフラストラクチャ層（最外側）
 *
 * 使用方法: php scripts/check-dependency-direction.php src/
 */

$targetDir = $argv[1] ?? 'src';

if (!is_dir($targetDir)) {
    echo "ディレクトリが見つかりません: {$targetDir}\n";
    exit(1);
}

// 層の定義（内側 → 外側の順）
$layers = [
    'Domain' => 0,         // 最内側
    'UseCase' => 1,
    'Application' => 1,    // UseCase の別名
    'Adapter' => 2,
    'Interface' => 2,      // Adapter の別名
    'Infrastructure' => 3, // 最外側
];

// 禁止される依存方向: 内側の層から外側の層への use 文
$forbiddenImports = [
    'Domain' => ['Infrastructure', 'Adapter', 'Interface', 'UseCase', 'Application'],
    'UseCase' => ['Infrastructure', 'Adapter', 'Interface'],
    'Application' => ['Infrastructure', 'Adapter', 'Interface'],
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

    // ファイルがどの層に属するか判定
    $currentLayer = null;
    foreach (array_keys($layers) as $layer) {
        if (str_contains($filePath, "/{$layer}/") || str_contains($filePath, "\\{$layer}\\")) {
            $currentLayer = $layer;
            break;
        }
    }

    if ($currentLayer === null || !isset($forbiddenImports[$currentLayer])) {
        continue;
    }

    // use 文を解析
    preg_match_all('/^use\s+([\w\\\\]+);/m', $content, $useMatches);
    foreach ($useMatches[1] as $import) {
        foreach ($forbiddenImports[$currentLayer] as $forbidden) {
            if (str_contains($import, "\\{$forbidden}\\")) {
                $violations[] = sprintf(
                    "%s — %s 層が %s 層に依存しています（use %s）。"
                    . "\n    依存方向違反: 内側の層は外側の層に依存してはなりません。"
                    . " 依存性逆転原則（DIP）を適用してください。",
                    $filePath,
                    $currentLayer,
                    $forbidden,
                    $import,
                );
            }
        }
    }

    // Domain 層での具体的な禁止パターン
    if ($currentLayer === 'Domain') {
        // PDO, Doctrine, Eloquent 等の永続化フレームワークの使用検出
        $infraPatterns = [
            'PDO' => 'PDO（データベース直接アクセス）',
            'Doctrine\\\\' => 'Doctrine（ORM）',
            'Illuminate\\\\Database' => 'Eloquent（ORM）',
            'Symfony\\\\Component\\\\HttpFoundation' => 'HTTP（フレームワーク）',
            'GuzzleHttp' => 'Guzzle（HTTPクライアント）',
            'Redis' => 'Redis（キャッシュ/ストレージ）',
            'Predis' => 'Predis（Redis クライアント）',
            'Aws\\\\' => 'AWS SDK',
        ];

        foreach ($infraPatterns as $pattern => $description) {
            if (preg_match('/use\s+.*' . $pattern . '/', $content)) {
                $violations[] = sprintf(
                    "%s — Domain 層で %s を使用しています。"
                    . "\n    永続化の無知（Persistence Ignorance）違反。"
                    . " ドメイン層はインフラストラクチャの詳細を知るべきではありません。",
                    $filePath,
                    $description,
                );
            }
        }
    }
}

if (empty($violations)) {
    echo "✅ 依存方向違反は見つかりませんでした。\n";
    exit(0);
}

echo "❌ 依存方向違反が見つかりました:\n\n";
foreach ($violations as $v) {
    echo "  • {$v}\n\n";
}
echo "違反数: " . count($violations) . "\n";
exit(1);
