# okite-ai → mago ルールマッピング

このドキュメントは [okite-ai スキル](https://github.com/endoumame/dot_agents/tree/main/.agents/skills/okite-ai) の全 43 スキルが mago のどのルールに対応するかを記載する。

## 凡例

| 状態 | 説明 |
|------|------|
| ✅ mago 内蔵 | mago.toml の linter/analyzer 設定で直接強制可能 |
| 🛡️ Guard | mago guard（Architectural Guard）で強制可能 |
| ⚠️ 部分的 | mago の既存ルールで一部のみ強制可能 |
| 🔧 カスタム | mago 単体では不可。補助スクリプト / PHPStan カスタムルールで対応 |
| 📋 レビュー | 静的解析では強制不可。コードレビューで担保 |

---

## 1. aggregate-design（集約設計）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| 不変性推奨 | 🛡️ Guard | `guard.structural: Aggregate → must_be_final` |
| 完全コンストラクタ | 🔧 カスタム | `scripts/check-complete-constructor.php` |
| 防御的コピー | 🔧 カスタム | `scripts/check-defensive-copy.php` |
| 不変条件の維持 | ✅ mago 内蔵 | `strict-types`, `check-missing-type-hints` |
| 集約ルート経由アクセス | 🔧 カスタム | `scripts/check-aggregate-access.php` |
| 集約を小さく保つ | ✅ mago 内蔵 | `too-many-properties` (≤7), `too-many-methods` (≤10) |
| 他集約はIDで参照 | 📋 レビュー | — |
| 永続化の無知 | 🔧 カスタム | `scripts/check-persistence-ignorance.php` |
| Entity は final | 🛡️ Guard | `guard.structural: Entity → must_be_final` |
| Event は不変 | 🛡️ Guard | `guard.structural: Event → must_be_final, must_be_readonly` |

## 2. aggregate-transaction-boundary（トランザクション境界）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| 1トランザクション=1集約 | 🔧 カスタム | `scripts/check-transaction-boundary.php` |
| イベントは不変の記録 | 🛡️ Guard | `guard.structural: Event → must_be_final, must_be_readonly` |
| 結果整合性 | 📋 レビュー | — |
| ドメインイベントによる連携 | 📋 レビュー | — |

## 3. backward-compat-governance（後方互換性ガバナンス）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| 非推奨パターン排除 | ✅ mago 内蔵 | `deprecated-*` (全7ルール) |
| バージョン分岐の検出 | 🔧 カスタム | `scripts/check-version-branching.php` |
| 互換層の局所化 | 📋 レビュー | — |

## 4. breach-encapsulation-naming（カプセル化破壊の命名）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| get プレフィックス禁止 | 🔧 カスタム | `scripts/check-no-getter.php` |
| breachEncapsulationOf 強制 | 🔧 カスタム | `scripts/check-breach-naming.php` |
| breach + if の検出 | 🔧 カスタム | `scripts/check-breach-misuse.php` |

## 5. clean-architecture（クリーンアーキテクチャ）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| 依存方向の制約 | 🛡️ Guard | `guard.perimeter: layering + rules（5層）` |
| ドメイン層の外部依存排除 | 🛡️ Guard | `guard.perimeter: Domain → permit core/psr/domain-libs のみ` |
| フレームワーク依存の局所化 | 🛡️ Guard | `guard.perimeter: framework は Adapter/Infrastructure のみ` |
| Repository実装の配置 | 🛡️ Guard | `guard.structural: Infrastructure\\Repository → must_be_final` |
| コントローラは final | 🛡️ Guard | `guard.structural: Controller → must_be_final, *Controller` |
| ドメイン層の外部依存排除 | ✅ mago 内蔵 | `no-global`, `no-request-variable`, `no-request-all` |
| 名前空間必須 | ✅ mago 内蔵 | `require-namespace` |
| 1ファイル1クラス | ✅ mago 内蔵 | `single-class-per-file` |

## 6. cqrs-aggregate-modeling（CQRS集約モデリング）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| コマンドは不変 | 🛡️ Guard | `guard.structural: Command → must_be_final, must_be_readonly, *Command` |
| クエリは不変 | 🛡️ Guard | `guard.structural: Query → must_be_final, must_be_readonly, *Query` |
| ハンドラは final | 🛡️ Guard | `guard.structural: Handler → must_be_final, *Handler` |
| コマンド検証に必要な最小状態 | ✅ mago 内蔵 | `too-many-properties` (≤7) |
| クエリモデルの分離 | 📋 レビュー | — |

## 7. cqrs-to-event-sourcing（CQRSからイベントソーシングへ）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| イベントの単一ソース化 | 📋 レビュー | — |

## 8. cqrs-tradeoffs（CQRSトレードオフ）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| 読み書きモデルの分離 | 📋 レビュー | — |

## 9. cross-aggregate-constraints（集約間制約）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| コマンドがクエリに依存しない | 🔧 カスタム | `scripts/check-cqrs-dependency.php` |

## 10. domain-building-blocks（ドメインビルディングブロック）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| 値オブジェクトの不変性 | 🛡️ Guard | `guard.structural: ValueObject → must_be_final, must_be_readonly` |
| コレクションの不変性 | 🛡️ Guard | `guard.structural: Collection → must_be_final, must_be_readonly` |
| ドメインサービスは final | 🛡️ Guard | `guard.structural: Service → must_be_final` |
| ドメイン例外は final | 🛡️ Guard | `guard.structural: Exception → must_be_final` |
| Domain に Trait 禁止 | 🛡️ Guard | `guard.structural: Domain → must_be [Class, Interface, Enum]` |
| リポジトリはインターフェース | 🛡️ Guard | `guard.structural: Repository → must_be [Interface], *Repository` |
| ファクトリはインターフェース | 🛡️ Guard | `guard.structural: Factory → must_be [Interface], *Factory` |
| 型安全性 | ✅ mago 内蔵 | `strict-types`, `check-missing-type-hints` |
| エンティティのID識別 | 🔧 カスタム | `scripts/check-entity-identity.php` |
| ドメインサービスの状態なし | 🔧 カスタム | `scripts/check-stateless-service.php` |

## 11. domain-model-first（ドメインモデル中心）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| テストファーストアプローチ | ✅ mago 内蔵 | PHPUnit integration |
| インフラ排除 | ✅ mago 内蔵 | `no-global`, `no-request-variable` |

## 12. domain-primitives-and-always-valid（ドメインプリミティブ）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| 値オブジェクトは不変 | 🛡️ Guard | `guard.structural: ValueObject → must_be_final, must_be_readonly` |
| DTO は不変 | 🛡️ Guard | `guard.structural: DTO → must_be_final, must_be_readonly` |
| strict_types 必須 | ✅ mago 内蔵 | `strict-types` (error) |
| 全関数に型宣言 | ✅ mago 内蔵 | `check-missing-type-hints` |
| クロージャにも型宣言 | ✅ mago 内蔵 | `check-closure-missing-type-hints` |
| アロー関数にも型宣言 | ✅ mago 内蔵 | `check-arrow-function-missing-type-hints` |
| 安全でない比較の禁止 | ✅ mago 内蔵 | `identity-comparison`, `no-insecure-comparison` |
| 暗黙的型変換の禁止 | ✅ mago 内蔵 | `no-short-bool-cast`, `no-empty` |
| settype 禁止 | ✅ mago 内蔵 | `disallowed-functions` |
| プリミティブラップ | 🔧 カスタム | `scripts/check-primitive-wrapping.php` |

## 13. error-classification（エラー分類）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| 空 catch の禁止 | ✅ mago 内蔵 | `no-empty-catch-clause` |
| エラー制御演算子禁止 | ✅ mago 内蔵 | `no-error-control-operator` |
| die/exit 禁止 | ✅ mago 内蔵 | `disallowed-functions` |
| trigger_error 禁止 | ✅ mago 内蔵 | `disallowed-functions` |
| unsafe finally 禁止 | ✅ mago 内蔵 | `no-unsafe-finally` |

## 14. error-handling（エラーハンドリング）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| Result/Either 型の使用 | 🔧 カスタム | `scripts/check-result-type.php` |
| else 禁止（早期リターン） | ✅ mago 内蔵 | `no-else-clause` |
| 条件内の代入禁止 | ✅ mago 内蔵 | `no-assign-in-condition` |
| 引数内の代入禁止 | ✅ mago 内蔵 | `no-assign-in-argument` |

## 15. first-class-collection（ファーストクラスコレクション）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| コレクションは不変 | 🛡️ Guard | `guard.structural: Collection → must_be_final, must_be_readonly` |
| コレクションのラップ | 🔧 カスタム | `scripts/check-first-class-collection.php` |
| 不変性の確保 | ✅ mago 内蔵 | `strict-types` |

## 16. intent-based-dedup（意図に基づく重複排除）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| 冗長コードの検出 | ✅ mago 内蔵 | `no-redundant-*` (全25ルール) |
| 意図の不一致検出 | 📋 レビュー | — |

## 17. law-of-demeter（デメテルの法則）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| メソッドチェーン制限 | 🔧 カスタム | `scripts/check-method-chain.php` |
| 過剰ネスト禁止 | ✅ mago 内蔵 | `excessive-nesting` |
| ネスト三項演算子禁止 | ✅ mago 内蔵 | `no-nested-ternary` |

## 18. parse-dont-validate（バリデーションではなくパース）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| 型安全なパース | ✅ mago 内蔵 | `strict-types`, `check-missing-type-hints` |
| 変数の変数を禁止 | ✅ mago 内蔵 | `no-variable-variable` |
| 厳格な配列チェック | ✅ mago 内蔵 | `strict-list-index-checks` |
| 未定義キー不許容 | ✅ mago 内蔵 | `allow-possibly-undefined-array-keys = false` |

## 19. tell-dont-ask（命じよ、尋ねるな）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| bool フラグパラメータ禁止 | ✅ mago 内蔵 | `no-boolean-flag-parameter` |
| 静的クロージャ推奨 | ✅ mago 内蔵 | `prefer-static-closure` |
| empty() 禁止 | ✅ mago 内蔵 | `no-empty` |
| getter の検出 | 🔧 カスタム | `scripts/check-no-getter.php` |

## 20. when-to-wrap-primitives（プリミティブラップ判断）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| 型宣言の強制 | ✅ mago 内蔵 | `check-missing-type-hints` |
| プリミティブ型の直接使用検出 | 🔧 カスタム | `scripts/check-primitive-wrapping.php` |

## 21. package-design（パッケージ設計）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| レイヤー間依存の制御 | 🛡️ Guard | `guard.perimeter: 5層レイヤリング` |
| 名前空間必須 | ✅ mago 内蔵 | `require-namespace` |
| 1ファイル1クラス | ✅ mago 内蔵 | `single-class-per-file` |
| ファイル名一貫性 | ✅ mago 内蔵 | `file-name` |
| 未使用定義の検出 | ✅ mago 内蔵 | `find-unused-definitions` |
| 循環依存の検出 | 🔧 カスタム | `scripts/check-circular-dependency.php` |

## 22. repository-design（リポジトリ設計）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| Domain にインターフェース定義 | 🛡️ Guard | `guard.structural: Domain\\Repository → must_be [Interface], *Repository` |
| 実装は Infrastructure に配置 | 🛡️ Guard | `guard.structural: Infrastructure\\Repository → must_be_final, *Repository` |
| 命名規約（詳細） | 🔧 カスタム | `scripts/check-repository-naming.php` |
| CQS 遵守 | 🔧 カスタム | `scripts/check-repository-cqs.php` |
| Repository 間の呼び出し禁止 | 🔧 カスタム | `scripts/check-repository-isolation.php` |

## 23. repository-placement（リポジトリ配置）

| 原則 | mago 対応 | ルール名 |
|------|-----------|---------|
| インターフェースは Domain 層 | 🛡️ Guard | `guard.structural: Domain\\Repository → must_be [Interface]` |
| 実装は Infrastructure 層 | 🛡️ Guard | `guard.perimeter + guard.structural` |
| レイヤー配置の検証 | 🔧 カスタム | `scripts/check-layer-placement.php` |

## 24-43. その他のスキル

| スキル | mago 対応 | 主要ルール |
|--------|-----------|-----------|
| ddd-module-pattern | ✅ + 🔧 | `require-namespace`, カスタム |
| refactoring-packages | ✅ | `find-unused-definitions`, `no-redundant-*` |
| custom-linter-creator | — | メタスキル（本ドキュメント自体が出力） |
| domain-model-extractor | 📋 | レビュー専用 |
| creating-rules | — | メタスキル |
| reviewing-skills | — | メタスキル |
| deepresearch-readme | — | 非コードスキル |
| openspec-* | — | 非コードスキル |
| migrate-skill-to-agent | — | 非コードスキル |
| pekko-cqrs-es-implementation | 📋 | Scala/Pekko 専用 |

---

## 統計

| カテゴリ | ルール数 |
|----------|---------|
| ✅ mago 内蔵（linter + analyzer） | 117 ルール |
| 🛡️ mago guard（Architectural Guard） | Perimeter: 5層 + 5ルール / Structural: 22 ルール |
| 🔧 カスタムスクリプトで補完 | 18 スクリプト |
| 📋 コードレビューで担保 | 12 原則 |

### Guard で新たに機械的に強制可能になった原則

以前は 🔧カスタム / 📋レビュー だったものが 🛡️Guard で自動強制に昇格:

| 原則 | 旧 | 新 | Guard ルール |
|------|-----|-----|-------------|
| 依存方向の制約 | 🔧 カスタム | 🛡️ Guard | `guard.perimeter: layering` |
| ドメイン層の外部依存排除 | 🔧 カスタム | 🛡️ Guard | `guard.perimeter: Domain permit` |
| 値オブジェクトの不変性 | ⚠️ 部分的 | 🛡️ Guard | `guard.structural: must_be_readonly` |
| コレクションの不変性 | 🔧 カスタム | 🛡️ Guard | `guard.structural: must_be_readonly` |
| Entity/Aggregate は final | ⚠️ 部分的 | 🛡️ Guard | `guard.structural: must_be_final` |
| リポジトリの配置 | 🔧 カスタム | 🛡️ Guard | `guard.perimeter + structural` |
| コマンド/クエリは不変 | 📋 レビュー | 🛡️ Guard | `guard.structural: must_be_readonly` |
| Domain に Trait 禁止 | 📋 レビュー | 🛡️ Guard | `guard.structural: must_be` |
