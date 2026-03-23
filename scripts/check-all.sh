#!/usr/bin/env bash
# =============================================================================
# okite-ai 全チェック実行スクリプト
# =============================================================================
#
# mago の組み込みルール + カスタムチェックスクリプトを一括実行する。
#
# 使用方法:
#   ./scripts/check-all.sh [target-dir]
#   ./scripts/check-all.sh src/Domain
#
# =============================================================================

set -euo pipefail

TARGET_DIR="${1:-src}"
DOMAIN_DIR="${2:-src/Domain}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
EXIT_CODE=0

echo "================================================================="
echo " okite-ai × mago 統合チェック"
echo "================================================================="
echo ""
echo "対象ディレクトリ: ${TARGET_DIR}"
echo "ドメイン層: ${DOMAIN_DIR}"
echo ""

# ----- Phase 1: mago 組み込みチェック -----
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Phase 1: mago lint（組み込みルール 117 ルール）"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if command -v mago &> /dev/null; then
    echo "▶ mago lint 実行中..."
    if ! mago lint; then
        echo "❌ mago lint に失敗しました。"
        EXIT_CODE=1
    else
        echo "✅ mago lint 完了。"
    fi

    echo ""
    echo "▶ mago analyze 実行中..."
    if ! mago analyze; then
        echo "❌ mago analyze に失敗しました。"
        EXIT_CODE=1
    else
        echo "✅ mago analyze 完了。"
    fi

    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "Phase 1.5: mago guard（Architectural Guard: 依存方向 + 構造規約）"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "▶ mago guard 実行中..."
    if ! mago guard; then
        echo "❌ mago guard に失敗しました。"
        EXIT_CODE=1
    else
        echo "✅ mago guard 完了。"
    fi
else
    echo "⚠️  mago がインストールされていません。"
    echo "   インストール: cargo install mago"
    echo "   または: https://mago.carthage.software/guide/installation"
    EXIT_CODE=1
fi

echo ""

# ----- Phase 2: カスタムチェック -----
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Phase 2: okite-ai カスタムチェック（18 スクリプト）"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

run_check() {
    local script="$1"
    local args="$2"
    local name
    name="$(basename "$script" .php)"

    echo ""
    echo "▶ ${name} 実行中..."
    if php "${script}" ${args}; then
        echo "  ✅ ${name} 完了。"
    else
        echo "  ❌ ${name} に違反が見つかりました。"
        EXIT_CODE=1
    fi
}

# breach-encapsulation-naming / tell-dont-ask
run_check "${SCRIPT_DIR}/check-no-getter.php" "${DOMAIN_DIR}"

# aggregate-transaction-boundary
run_check "${SCRIPT_DIR}/check-transaction-boundary.php" "${TARGET_DIR}"

# clean-architecture / repository-placement
run_check "${SCRIPT_DIR}/check-dependency-direction.php" "${TARGET_DIR}"

# law-of-demeter
run_check "${SCRIPT_DIR}/check-method-chain.php" "${DOMAIN_DIR}"

# repository-design
run_check "${SCRIPT_DIR}/check-repository-naming.php" "${TARGET_DIR}"

# aggregate-design / domain-building-blocks
run_check "${SCRIPT_DIR}/check-aggregate-design.php" "${DOMAIN_DIR}"

# domain-primitives-and-always-valid / parse-dont-validate
run_check "${SCRIPT_DIR}/check-domain-primitives.php" "${DOMAIN_DIR}"

# first-class-collection
run_check "${SCRIPT_DIR}/check-first-class-collection.php" "${DOMAIN_DIR}"

echo ""
echo "================================================================="
if [ $EXIT_CODE -eq 0 ]; then
    echo " ✅ 全チェック完了。違反なし。"
else
    echo " ❌ チェック失敗。上記の違反を修正してください。"
fi
echo "================================================================="

exit $EXIT_CODE
