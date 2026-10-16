# Release checklist

Use this checklist only after the blockers in `release-readiness.md` are closed. It does not authorize publication, tagging, or remote changes.

## Findings behind the checklist

| ID | Severity | Concrete evidence | Impact | Recommendation | Blocks first publication |
|---|---|---|---|---|---|
| REL-001 | Resolved (was Critical) | The owner approved MIT; `LICENSE` names LZK Tools and Composer declares `MIT`. | Distribution rights for the Free package are explicit. | Preserve the notice in every source and archive distribution. | No |
| REL-002 | Resolved (was High) | README now follows the current facade/configuration API and documentation tests parse PHP examples and verify references. | The obsolete onboarding conflict is removed. | Keep examples executable as the API evolves. | No |
| REL-003 | High | Local PHP 8.2/8.4, Laravel 12/13, current/minimum and SQLite rows pass, but no CI workflow, PHP 8.3/8.5, PostgreSQL or MySQL execution exists. | Release quality still depends on one workstation and database engine. | Automate the executed rows, add the missing runtimes and isolated database services, and preserve artifacts/results. | **Yes for broad support claims** |
| REL-004 | Resolved locally; publication verification pending (High) | `SECURITY.md` identifies `lzktools@gmail.com` as the private reporting contact, and mailbox reception was verified on 2026-10-16. | A private email reporting channel is available; the updated policy is not yet published on GitHub. | Publish the updated policy and verify the contact is visible and correct. | **Pending verification** |
| REL-005 | Resolved for local candidate (was Medium) | Git and Composer exclusions are defined. The first local archive exposed `vendor/` and `composer.lock`; explicit Composer exclusions fixed it, and the regenerated 62-file archive passed required/forbidden file checks. | The prepared archive surface is explicit and locally verified. | Repeat archive inspection from the future clean Git release candidate. | No |
| REL-006 | Medium | Changelog has phase-based Unreleased content but no version/date section. | Consumers cannot map changes to an immutable release. | Convert final entries to a versioned, dated section and comparison links when a remote exists. | Yes at release time |

## Pre-release decision

- [ ] Owner approves release type: preview `0.1.0` or stable target.
- [x] Owner approves MIT for the Free package; Pro remains a separate package with terms pending.
- [x] Supported recommended API/configuration and advanced provisional surface are listed.
- [ ] Release blockers have owners and closure evidence.
- [x] README separates declared constraints from actually executed compatibility.

## Repository and documentation

- [x] Add standard MIT `LICENSE` naming LZK Tools and Composer `license: MIT`.
- [x] Add `SECURITY.md` and a provisional support table.
- [x] Configure and verify a monitored private security contact (`lzktools@gmail.com`).
- [ ] Publish the updated `SECURITY.md` on GitHub and verify the reporting instructions.
- [x] Rewrite README around the current recommended API and operational requirements.
- [x] Mark advanced/provisional namespaces in `docs/public-api.md`.
- [x] Parse README PHP examples and verify document references in `DocumentationTest`.
- [x] Re-run the independent Laravel 12 demo tests against the current path package (8 tests, 33 assertions).
- [x] Update decisions, verification, changelog, release readiness, security and distribution guidance for Phase 3C.3.
- [ ] Add package metadata such as authors/support/source/homepage only after real destinations exist.
- [x] Review Git and Composer export rules and inspect the corrected 62-file local archive.

## Quality gates

- [x] Pin third-party GitHub Actions to full commit SHAs resolved from their official repositories.
- [ ] Clean install on every required matrix row.
- [x] `composer validate --strict --no-check-publish` passes locally.
- [x] `composer audit --locked` reports no advisories locally.
- [x] `composer licenses --no-dev` reviewed; runtime metadata is permissive or offers a permissive choice.
- [x] PHPUnit passes locally.
- [x] Larastan/PHPStan passes locally.
- [x] PHPCS PSR-12 passes locally.
- [x] Minimum-dependency resolutions pass for Laravel 12 and 13 on executed PHP rows.
- [x] SQLite rows pass for PHP 8.2/Laravel 12 and PHP 8.4/Laravel 12-13.
- [ ] PostgreSQL/MySQL rows pass before those engines are claimed.
- [x] Fresh Laravel 12 application installs the tar without path/symlink and its 7 consumer tests/command pass.
- [ ] No bootstrap query and no DML regression tests pass.
- [ ] Query/binding snapshots for OR predicates and scopes pass.
- [ ] Performance baseline is attached if performance claims are made.

## Security and privacy gate

- [x] Application scope and tenant authorization responsibility is prominent.
- [x] Exact total work is bounded; terminal reports expose conservative truncation.
- [x] Canonical-column lifecycle and representative blocking indexes are documented.
- [x] Built-in result privacy boundary is documented and covered by existing tests.
- [x] Security policy warns that SQL logging can expose bindings.
- [x] Current locked dependency audit reports no advisories.
- [x] A read-only database role is recommended for high-assurance deployments.
- [ ] Threat model and disclosure contact have an owner.

## Packaging and SemVer

- [x] Provisional 0.x SemVer policy and breaking-change categories are documented.
- [ ] Changelog has the exact version and release date.
- [ ] Composer constraints match the tested matrix.
- [x] Local 0.1.0 tar installs into a clean Laravel 12 application through Composer package/dist metadata, without path or symlink.
- [x] Local candidate archive contains README, license, changelog, config, source, security policy, and docs.
- [x] Corrected local candidate archive excludes vendor, lock, CI metadata, local tools, tests, caches and development configuration.
- [ ] Tag name, package version, and changelog agree.
- [ ] Release approval is recorded before any tag or publication.
- [ ] After publication, verify install from the public channel and do not mutate the tag/archive.

## Rollback and follow-up

Composer releases are immutable; a bad release is corrected by a new patch version and, where supported, marking the bad version abandoned with an explanation. Prepare a communication template, security escalation owner, and patch branch before publication. Schedule a post-release dependency audit and review of installation failures.
