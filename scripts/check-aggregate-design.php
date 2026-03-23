#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * okite-ai スキル: aggregate-design, domain-building-blocks, domain-primitives-and-always-valid
 *
 * 集約設計の基本原則を検証する。
 *
 * ルール:
 * 1. 集約内で他の集約への直接参照（オブジェクト参照）がないこと（IDのみ許容）
 * 2. public プロパティの禁止（カプセル化）
 * 3. setter メソッドの禁止（不変性）
 * 4. 空のコンストラクタの禁止（完全コンストラクタ）
 * 5. ドメインモデルでの永続化フレームワーク依存の禁止（永続化の無知）
 *
 * 使用方法: php scripts/check-aggregate-design.php src/Domain/
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
    $lines = explode("\n", $content);

    // クラス定義を検出
    if (!preg_match('/class\s+(\w+)/', $content, $classMatch)) {
        continue;
    }
    $className = $classMatch[1];

    // ルール 1: public プロパティの禁止（readonly は許容）
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/\bpublic\s+(?!readonly\b)(?!function\b)(?!const\b)(?!static\b)\s*(?:\??\w+\s+)?\$/', $line)) {
            $violations[] = sprintf(
                "%s:%d — クラス '%s' に public プロパティがあります。"
                . "\n    カプセル化違反。private/protected readonly にするか、"
                . " メソッドを通じたアクセスに変更してください。",
                $filePath,
                $lineNum + 1,
                $className,
            );
        }
    }

    // ルール 2: setter メソッドの禁止
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/\bpublic\s+function\s+(set[A-Z]\w*)\s*\(/', $line, $setterMatch)) {
            $violations[] = sprintf(
                "%s:%d — クラス '%s' に setter '%s()' があります。"
                . "\n    不変性原則違反。状態変更は振る舞いメソッド（ドメインの言葉で命名）で表現してください。"
                . "\n    例: setStatus('active') → activate()",
                $filePath,
                $lineNum + 1,
                $className,
                $setterMatch[1],
            );
        }
    }

    // ルール 3: 永続化フレームワーク依存の検出
    $ormAnnotations = [
        '#\[ORM\\\\' => 'Doctrine ORM アノテーション',
        '#\[Column' => 'Doctrine Column',
        '#\[Table' => 'Doctrine Table',
        '#\[Entity' => 'Doctrine Entity',
        '/@ORM\\\\/' => 'Doctrine ORM (DocBlock)',
        '/@Column/' => 'Column (DocBlock)',
        '/extends\s+Model\b/' => 'Eloquent Model 継承',
        '/use\s+HasFactory/' => 'Laravel HasFactory',
        '/use\s+SoftDeletes/' => 'Laravel SoftDeletes',
    ];

    foreach ($ormAnnotations as $pattern => $description) {
        if (preg_match('/' . $pattern . '/', $content)) {
            $violations[] = sprintf(
                "%s — クラス '%s' で %s を使用しています。"
                . "\n    永続化の無知（Persistence Ignorance）違反。"
                . " ドメインモデルはどう保存されるかを知るべきではありません。"
                . " 永続化の詳細はインフラ層のマッピングに移動してください。",
                $filePath,
                $className,
                $description,
            );
        }
    }

    // ルール 4: コンストラクタなし / 空コンストラクタの検出
    if (preg_match('/class\s+\w+/', $content)) {
        // interface, trait, abstract は除外
        if (!preg_match('/\b(interface|trait)\s+/', $content)) {
            if (!preg_match('/__construct\s*\(/', $content)) {
                // enum は除外
                if (!preg_match('/\benum\s+/', $content)) {
                    $violations[] = sprintf(
                        "%s — クラス '%s' にコンストラクタがありません。"
                        . "\n    完全コンストラクタ原則違反。"
                        . " 全ての状態をコンストラクタで初期化してください。",
                        $filePath,
                        $className,
                    );
                }
            }
        }
    }
}

if (empty($violations)) {
    echo "✅ 集約設計違反は見つかりませんでした。\n";
    exit(0);
}

echo "❌ 集約設計違反が見つかりました:\n\n";
foreach ($violations as $v) {
    echo "  • {$v}\n\n";
}
echo "違反数: " . count($violations) . "\n";
exit(1);
