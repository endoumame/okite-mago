# =============================================================================
# okite-ai × mago Makefile
# =============================================================================
#
# 使用方法:
#   make lint          — mago lint のみ実行
#   make analyze       — mago analyze のみ実行
#   make check-domain  — okite-ai カスタムチェックのみ実行
#   make check         — 全チェック実行（mago + カスタム）
#   make fix           — mago の自動修正を適用
#   make fmt           — mago フォーマッタを実行
#   make all           — fmt + check（CI 用）
# =============================================================================

.PHONY: lint analyze guard check-domain check fix fmt all install

TARGET_DIR ?= src
DOMAIN_DIR ?= src/Domain

# mago lint（組み込み 117 ルール）
lint:
	mago lint

# mago 静的解析
analyze:
	mago analyze

# mago guard（Architectural Guard: 依存方向 + 構造規約）
guard:
	mago guard

# mago 自動修正
fix:
	mago lint --fix

# mago フォーマット
fmt:
	mago fmt

# okite-ai カスタムドメインチェック
check-domain:
	@bash scripts/check-all.sh $(TARGET_DIR) $(DOMAIN_DIR)

# 全チェック実行（lint + analyze + guard + カスタム）
check: lint analyze guard check-domain

# CI 用: フォーマット + 全チェック
all: fmt check

# mago インストール
install:
	@echo "mago のインストール方法:"
	@echo "  cargo install mago"
	@echo "  または https://mago.carthage.software/guide/installation を参照"
