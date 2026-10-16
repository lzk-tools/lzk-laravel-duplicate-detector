# Security policy

## Current status

The package has no published release and no configured private vulnerability reporting channel. **Do not send vulnerability details, credentials, personal
data, database dumps, or exploit material to an invented address or public issue.**

A monitored private channel must be selected and published by the owner before
the first public distribution. Until that exists, the release-readiness security
finding remains unresolved. This file will be updated with the real channel; no
email address, repository feature, or service is implied today.

## Planned GitHub private reporting channel

When a real GitHub repository exists, an administrator should open **Settings →
Code security and analysis**, enable **Private vulnerability reporting**, and
verify that the repository Security tab exposes a private report flow. Repository
creation alone does not enable or verify this feature.

Until that verification is completed and this policy links to the real repository
channel, RR-007 remains unresolved. Public issues must not be used for vulnerability
details.
## Supported versions

There are currently no published or supported versions.

| Version | Supported |
| --- | --- |
| Unreleased development state | No public security support commitment |
| Planned `0.1.x` | Pending release and owner approval |

When releases exist, this table will identify maintained lines explicitly.
Users must not infer support from Composer constraints alone.

## How to report after a private channel is configured

Use only the private channel named in this file at that time. Include:

- affected package version or immutable commit identifier;
- PHP, Laravel, database engine, and relevant configuration;
- impact and required attacker privileges;
- minimal reproduction using fictitious data;
- affected classes/methods and observed queries where relevant;
- whether tenant isolation, scopes, blocking, report output, or read-only
  behavior is involved;
- suggested mitigation, if known;
- a safe way to contact the reporter.

Do not include real personal data, production credentials, tokens, or full
production database contents.

## Coordinated disclosure

Once a private channel exists, maintainers and reporters should coordinate
before public disclosure. The maintainer should acknowledge receipt, reproduce
and assess impact, prepare a fix and release note, and agree on disclosure timing
based on actual risk and release readiness. This policy does not promise a fixed
response or remediation deadline that the project cannot currently guarantee.

Public advisories should provide enough information to upgrade and mitigate
without unnecessarily enabling exploitation before a fix is available. Credit
is optional and requires reporter consent.

## Security model and limitations

The built-in package paths:

- require an explicit `scope()` call in the recommended API;
- clone and preserve the supplied Builder predicates, bindings, and applied
  global scopes;
- require SQL blocking and exclude the source record;
- validate simple column identifiers and bind values;
- bound fuzzy work and exact candidate work;
- issue SELECT/schema-introspection queries rather than INSERT, UPDATE, DELETE,
  merge, or upsert operations in verified tests;
- omit compared raw and normalized field values from built-in result DTOs.

These statements are implementation guarantees with limits:

- Calling `scope()` does not prove authorization or tenant isolation. The
  consuming application must supply the correct tenant/policy predicates and can
  deliberately remove global scopes.
- Blocking can produce false negatives. Partial results can omit candidates.
- Keyset pagination is not a database snapshot under concurrent writes.
- Model identifiers, rule IDs, field names, reason codes, and similarities can
  still be sensitive.
- SQL logging can expose bindings.
- Custom callbacks, models, normalizers, service-container replacements, and
  extensions are consumer code and may have side effects.
- “Read-only” is not a database permission boundary. High-assurance deployments
  should use credentials limited to required reads and metadata operations.
- The package does not authorize merges or make identity decisions.

## Consumer responsibilities

Consumers must:

1. authorize the source record and candidate scope;
2. enforce tenant isolation and test every configured model;
3. maintain and protect canonical blocking columns;
4. choose selective indexes and finite limits appropriate to measured workloads;
5. inspect partial-result metadata and avoid exhaustive claims;
6. authorize access to result identifiers and metadata;
7. redact query logs and operational telemetry;
8. monitor dependency advisories for the resolved application graph.

See [the detailed security review](docs/release/security-review.md) and
[release readiness](docs/release/release-readiness.md).
