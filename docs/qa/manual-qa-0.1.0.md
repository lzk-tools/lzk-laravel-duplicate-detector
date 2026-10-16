# Manual QA plan - 0.1.0 candidate

Status: prepared, not executed. All cases are Pending; automated tests do not approve them.
Package: /var/www/lzk-laravel-duplicate-detector. Consumer: /var/www/lzk-duplicate-detector-demo.

## Isolated setup

    cd /var/www/lzk-duplicate-detector-demo
    export QA_PHP_INI_SCAN_DIR=:/var/www/lzk-laravel-duplicate-detector/.tools/sqlite/conf.d
    export QA_DB=/tmp/lzk-duplicate-detector-qa-0.1.0.sqlite
    test "$QA_DB" = /tmp/lzk-duplicate-detector-qa-0.1.0.sqlite
    rm -f "$QA_DB" && touch "$QA_DB"
    PHP_INI_SCAN_DIR="$QA_PHP_INI_SCAN_DIR" DB_CONNECTION=sqlite DB_DATABASE="$QA_DB" php artisan migrate:fresh --seed

Never use the existing demo database. Preserve commands, output, timestamp, revision, package version and QA DB path as evidence; redact secrets and compared values. Existing IDs: 1 source tenant 10; 2 normalized exact match; 3 fuzzy contradiction; 4 cross-tenant; 5 no match; 6 blocking false negative. All data is fictitious.

## Checklist

| ID | Objective | Preparation and exact steps | Expected result | Observed result | Status | Evidence | Observations |
|---|---|---|---|---|---|---|---|
| MQ-001 | Installation | Fresh demo copy; composer install; package:discover; composer show package | Resolves and discovers provider; no copied package code | | Passed | | |
| MQ-002 | Published config | Save prior checksum; vendor:publish provider/tag; compare docs | No DB query; blocking, limits, threshold and policy order clear | | Pending | | |
| MQ-003 | Baseline | Run isolated migrate:fresh --seed; inspect IDs/tenant/canonical fields | Six deterministic synthetic rows | | Pending | | |
| MQ-004 | Exact duplicate | Baseline; duplicates:detect 1 | Candidate 2, exact rule, exact-candidate, zero contradiction | | Pending | | |
| MQ-005 | Email normalization | IDs 1/2; inspect safe DTO projection | Case/space variants match; compared values absent | | Pending | | |
| MQ-006 | Text/Unicode | IDs 1-3; inspect fuzzy name comparison | Deterministic [0,1], Unicode safe; not probability | | Pending | | |
| MQ-007 | Fuzzy match | Run ID 1; locate candidate 3 fuzzy row | Candidate 3 meets configured 0.75 threshold | | Pending | | |
| MQ-008 | Contradiction | Inspect candidate 3 outcomes/codes | Safe contradiction visible; raw external ID absent | | Pending | | |
| MQ-009 | Classification | Compare candidates 2/3 | exact-candidate/manual-review; first complete rule wins | | Pending | | |
| MQ-010 | Tenant isolation | Run ID 1; inspect all candidates | Tenant-20 ID 4 absent | | Pending | | |
| MQ-011 | Mandatory scope | Consume detect without scope | LogicException before query; no write | | Pending | | |
| MQ-012 | Invalid scope | Return User builder from scope; consume | InvalidArgumentException | | Pending | | |
| MQ-013 | Candidate limit | Authorized config max 3/batch 2 plus five same-block rows; consume/log SQL | Three examined; terminal false/3/max_candidates_reached; no extra page | | Pending | | Needs fixture/config authorization |
| MQ-014 | Empty partial | MQ-013 plus blocking nonmatches; retain empty reports | Empty terminal false/3/reason; not exhaustive | | Pending | | Needs fixture/config authorization |
| MQ-015 | Report composition | Partial exact plus fuzzy; record every metadata field | Exact first; intermediate/fuzzy null; terminal metadata exact-only | | Pending | | Needs fixture/config authorization |
| MQ-016 | No match | duplicates:detect 5 | Success and privacy-safe no-match message | | Pending | | |
| MQ-017 | Invalid IDs | Run 0, -1, abc, unused positive; record exits | Clear validation/not-found messages; no trace | | Pending | | |
| MQ-018 | Empty data | Attempt blank email then blank name via Eloquent | Canonicalizer rejects; no partial insert | | Pending | | |
| MQ-019 | Repeatability | Run ID 1 three times and DTO inspection twice | Same order, rules, labels, contradictions, metrics | | Pending | | |
| MQ-020 | Read-only | Snapshot hash/rows/timestamps; detect IDs 1/5; inspect SQL; repeat | No INSERT/UPDATE/DELETE; persisted data unchanged | | Pending | | Explain file delta |
| MQ-021 | Privacy | Search captured output for seeded values | IDs and safe codes only; no compared values | | Pending | | IDs remain sensitive |
| MQ-022 | Invalid config | Authorized copies: limits 0/-1/string, threshold 1.1, aliases, missing block/no engine; log SQL | InvalidArgumentException; no contact query during compile | | Pending | | Needs config authorization |
| MQ-023 | Unknown key | Authorized harmless typo; clear cache/resolve; compare RR-006 | Actual ignore/reject behavior recorded as provisional | | Pending | | Needs config authorization |
| MQ-024 | Stale canonical | Authorized direct synthetic update; detect; recreate DB | Can omit/include candidate; package does not repair | | Pending | | Needs direct-SQL authorization |
| MQ-025 | Blocking false negative | Run ID 1; compare ID 1/6 name blocks | ID 6 absent because torres/torrez blocks differ | | Pending | | |
| MQ-026 | Exact+fuzzy | Consume all reports; count candidate 2 per rule | May occur once per engine; exact first; evidence separate | | Pending | | Consumer aggregates |

## Safe DTO inspection

Start Tinker with the isolated environment. Use DuplicateDetector::for(Contact::class), scope an Eloquent Builder to tenant 10, and detect Contact 1. Record per report: searchComplete, candidatesExamined, incompleteReason. Record per match only: candidateKey, ruleId, matchedFields, FieldOutcome values, contradiction codes, field metric/threshold/similarity/outcome, and classification label/policy/rule/reasons/evidence. Never dump model attributes or normalized values.

## Proposed additions - not applied

The current six rows cover MQ-003-012, MQ-016-021 and MQ-025-026. Partial cases need a tenant-9100 source, five ordered same-block candidates, three blocking nonmatches, near-threshold fuzzy candidates, cross-tenant candidate, empty/contradictory optional IDs, and stale canonical fields.

Minimal demo proposal: ManualQaContactSeeder guarded to SQLite and /tmp; InspectDuplicateReports command with safe projection/terminal metadata; opt-in QA config for small limits. No demo change was applied.

## Developer experience and exit criteria

A new developer must explain scope/authorization ownership, canonical columns/indexes, blocking/limit false negatives, similarity versus contradiction/classification, partial/empty terminal reports, cross-engine duplicates, sensitive metadata, and absence of merge authorization. Record source lookups as documentation usability defects.

Risks outside this run: SQLite does not prove PostgreSQL/MySQL; unknown keys and error taxonomy are provisional; demo command hides partial metadata; local install uses a path repository.

QA completes only after human results/evidence exist, failures have dispositions and temporary changes are removed. Start with MQ-001, then MQ-002 and MQ-003.


## MQ-001 preparation finding: Composer dist checksum algorithm

- Finding ID: MQ-001-F01
- Status: Open until the retry is observed; MQ-001 remains Pending.
- Scope: QA-only Composer repository metadata, not package source or archive content.
- Observation: the first manual definition placed the verified SHA-256 value in
  dist.shasum. Composer interprets dist.shasum as SHA-1 for this package metadata,
  so installation rejected the otherwise valid archive.
- Verified archive SHA-256:
  e4613be1b85091c9cc25d908c180d4a8814023d68667a05bdfc436868a3dd82e
- Verified Composer dist.shasum SHA-1:
  a295c80aeec0387c092079036b4c32b1984b313f
- Correction: keep SHA-256 as independent release-integrity evidence and use the
  SHA-1 value only in the temporary type: package dist.shasum field.
- Impact: the failed attempt provides no evidence against package code,
  autoloading, Laravel discovery, or archive contents because extraction/install
  did not complete.
- Retry isolation: use /tmp/lzk-duplicate-detector-mq-001-retry and do not reuse
  or delete the failed QA application.

**Estado:** Resolved

**Causa:** se utilizó un checksum SHA-256 en el campo
`dist.shasum` de una definición temporal Composer `type: package`,
que requiere SHA-1.

**Resolución:** se corrigió la metadata temporal y se repitió
la instalación desde cero en una nueva aplicación Laravel 12.
Los 12 pasos de MQ-001 finalizaron satisfactoriamente.

**Impacto en el paquete:** ninguno. No fue necesario modificar
el código fuente, `composer.json` del paquete ni el candidato `0.1.0`.


### Resultado MQ-001 — Instalación independiente

**Estado:** Passed
**Fecha:** 2026-10-14
**Entorno:** Laravel 12 / PHP 8.4.24
**Paquete:** lzk/laravel-duplicate-detector 0.1.0

**Resultado de la ejecución manual:**

- Integridad SHA-256 del candidato verificada.
- SHA-1 de la distribución verificado.
- Aplicación Laravel 12 creada en un entorno independiente.
- Instalación mediante archivo `tar` local completada.
- Ausencia de repositorios Composer `path`.
- `composer.lock` verificado: versión, URL, tipo y checksum.
- Instalación en `vendor/` sin symlinks.
- Autoload de las clases del paquete verificado.
- Service Provider registrado mediante package discovery.
- Versión `0.1.0` confirmada mediante Composer.

**Evidencia:** ejecución manual confirmada por el propietario
de los 12 pasos del procedimiento MQ-001.

**Limitación:** no se conservaron logs completos de cada comando.
Esta prueba utiliza metadata Composer local y no equivale
a una instalación desde Packagist.
