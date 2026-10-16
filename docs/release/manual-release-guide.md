# Manual release guide

This guide prepares a release but does not authorize a tag, GitHub repository,
Packagist publication, or public release. The owner must approve those actions.

## 1. Establish release infrastructure

1. Create or select the real GitHub repository and configure its default branch.
2. Push the reviewed source only after owner approval.
3. In GitHub, open **Settings → Code security and analysis** and enable
   **Private vulnerability reporting**. The setting is repository-specific and
   requires administrative permission.
4. Confirm the Security tab offers a private report flow from a non-admin test
   account. Only then update `SECURITY.md` with that verified channel and close
   RR-007.
5. Replace major action tags with reviewed immutable commit SHAs, then protect the`n   default branch and require the `Quality` workflow plus every
   mandatory `Compatibility` matrix row.

## 2. Prepare a candidate

1. Start from a clean checkout. Confirm there are no credentials, database files,
   caches, local tools, generated reports, or unrelated files.
2. Choose the version under the documented 0.x SemVer policy.
3. Move relevant `Unreleased` changelog entries into a dated version section.
4. Confirm `composer.json`, `LICENSE`, README, security policy, supported API and
   compatibility claims agree.
5. Resolve both current and minimum dependency rows through CI. Experimental rows
   provide evidence but are not release gates until promoted explicitly.

## 3. Required verification

Run on the release candidate:

```bash
composer validate --strict --no-check-publish
composer audit --locked
composer licenses --no-dev
composer test
composer analyse
composer test:style
```

Run the independent demo from the candidate source. Database support may only be
claimed for engines whose dedicated integration tests passed against identified,
isolated server versions.

## 4. Inspect the distributable archive

```bash
composer archive --format=tar --dir=/tmp/lzk-release-candidate
mkdir /tmp/lzk-release-inspection
tar -xf /tmp/lzk-release-candidate/*.tar -C /tmp/lzk-release-inspection
find /tmp/lzk-release-inspection -type f | sort
```

The archive must contain `LICENSE`, `README.md`, `CHANGELOG.md`, `composer.json`,
`config/`, `src/`, and required user documentation. It must exclude `vendor/`, `composer.lock`, `.github/`,
`.tools/`, test and analysis configuration, caches, credentials, databases, and
local artifacts. Install that archive in a clean Laravel application before a tag.

## 5. Approval and publication boundary

Record owner approval for the exact version, commit, changelog, compatibility
claim, archive checksum, license, and security channel. Only after that approval
may a maintainer create an immutable tag, GitHub release, or Packagist entry.
Never replace an existing tag or archive; correct a bad release with a new patch.

A future Pro product remains a separate package with separately approved terms.
The MIT license of this Free package does not define the Pro license.
