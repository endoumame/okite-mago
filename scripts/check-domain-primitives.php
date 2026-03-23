#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * okite-ai スキル: domain-primitives-and-always-valid, when-to-wrap-primitives, parse-dont-validate
 *
 * ドメインモデルでのプリミティブ型の直接使用を検出する。
 *
 * ルール:
 * 1. ドメインメソッドの引数に string, int, float, bool を直接使用しない
 *    （コンストラクタは例外として許容 — ファクトリメソッドでのラップを前提）
 * 2. 戻り値に string, int, float, bool を直接使用しない
 *    （breachEncapsulationOf* メソッドは例外）
 * 3. validate* / check* メソッドが void/bool を返す場合は parse-dont-validate 違反
 *
 * 使用方法: php scripts/check-domain-primitives.php src/Domain/
 */

$targetDir = $argv[1] ?? 'src/Domain';

if (!is_dir($targetDir)) {
    echo "ディレクトリが見つかりません: {$targetDir}\n";
    exit(1);
}

$primitiveTypes = ['string', 'int', 'float', 'bool', 'array', 'mixed'];
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

    // インターフェース/トレイト/列挙型を対象から除外
    if (preg_match('/\b(interface|trait|enum)\s+/', $content)) {
        continue;
    }

    // クラス名を取得
    if (!preg_match('/class\s+(\w+)/', $content, $classMatch)) {
        continue;
    }
    $className = $classMatch[1];

    // 値オブジェクト自身のコンストラクタはプリミティブを受け取って良い
    $isValueObject = preg_match('/(ValueObject|VO|Id|Name|Email|Amount|Price|Quantity|Code|Status|Type)\b/', $className);

    foreach ($lines as $lineNum => $line) {
        // public メソッドの引数チェック
        if (preg_match('/\bpublic\s+function\s+(\w+)\s*\(([^)]*)\)/', $line, $methodMatch)) {
            $methodName = $methodMatch[1];
            $params = $methodMatch[2];

            // コンストラクタと値オブジェクトは除外
            if ($methodName === '__construct') {
                continue;
            }
            if ($isValueObject) {
                continue;
            }
            // breachEncapsulationOf はプリミティブを返して良い
            if (str_starts_with($methodName, 'breachEncapsulationOf')) {
                continue;
            }

            // 引数にプリミティブ型が含まれるか
            foreach ($primitiveTypes as $type) {
                if (preg_match('/\b' . $type . '\s+\$/', $params)) {
                    $violations[] = sprintf(
                        "%s:%d — '%s::%s()' の引数にプリミティブ型 '%s' を直接使用しています。"
                        . "\n    ドメインプリミティブ（値オブジェクト）でラップしてください。"
                        . "\n    例: string \$email → EmailAddress \$email",
                        $filePath,
                        $lineNum + 1,
                        $className,
                        $methodName,
                        $type,
                    );
                }
            }
        }

        // validate* / check* メソッドの戻り値チェック（parse-dont-validate）
        if (preg_match('/\bpublic\s+(?:static\s+)?function\s+(validate\w*|check\w*|isValid\w*)\s*\([^)]*\)\s*:\s*(void|bool)\b/', $line, $validateMatch)) {
            $violations[] = sprintf(
                "%s:%d — '%s::%s()' が %s を返しています。"
                . "\n    Parse Don't Validate 原則違反。"
                . " チェック結果を型で保持してください（Result/Either 型、または検証済み型を返す）。"
                . "\n    例: validate(string): bool → parse(string): ValidatedEmail",
                $filePath,
                $lineNum + 1,
                $className,
                $validateMatch[1],
                $validateMatch[2],
            );
        }
    }
}

if (empty($violations)) {
    echo "✅ ドメインプリミティブ違反は見つかりませんでした。\n";
    exit(0);
}

echo "❌ ドメインプリミティブ違反が見つかりました:\n\n";
foreach ($violations as $v) {
    echo "  • {$v}\n\n";
}
echo "違反数: " . count($violations) . "\n";
exit(1);
