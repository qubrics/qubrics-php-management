# Calendar source sync — management

Goal: Prepare a normal calendar-only management source removal PR under `qubrics/qubrics-sdk#344` rule 12. Source PR only; no merge or publication authorised.

Status: Coded, not merged. One slice fixed locally; PR CI and independent review remain before handback.

## Canonical provenance

- Split base and preserved `origin/archive/calendar`: `3db467c73fd555d98e9a9631fa8fb9b889006ee7`. Archive read only; never created or overwritten.
- Canonical SDK baseline: `086e6c40d2d0fb349d06811120aca504555ab6c6`; its `packages/php/management/src/` blobs exactly match split base `src/` (zero mismatches).
- Canonical SDK final: `4416e3cec4e5cff2440f7aff8cbd6e5cd4b70c5e`, `packages/php/management/src/`.
- Applied committed bytes with `git diff --binary <baseline> <final> -- packages/php/management/src`, then `git apply -p4`. No generated implementation hand edits.
- Canonical ownership: SDK `README.md` Packages table points PHP management source to `packages/php/management`; `scripts/publish-php-split.mjs` consumes this generated source. Publisher not invoked: it creates/pushes tags.
- Final source qualification: all 154 split `src/` files equal immutable canonical Git blobs; zero mismatches and zero calendar filenames/runtime text.
- Removes calendar webhook models, active discriminator and new-subscription label. Retains all eight mail discriminator subtypes. Historical delivery payloads remain opaque maps and historical event labels remain plain strings.
- Composer identity/dependencies, package version, changelog history, README and CI remain byte-for-byte unchanged. No tags, registry writes, release-please, uploads or live endpoint changes.

## Checks

Recorded 2026-11-20 11:28 AEDT. PHP 8.4.10; Composer 2.8.9; unchanged tracked `composer.json`; no tracked lockfile.

- `composer validate --strict`: exit 0.
- Existing CI syntax command over `src/` and `tests/`: exit 0.
- Existing CI candidate dependency setup with temporary `composer.ci.json`, immutable SDK `packages/php/core` archived into `.ci/core`, and `COMPOSER_DISABLE_NETWORK=1`: exit 0 using cached archives. Temporary manifest/lock/vendor are not committed. The generated loader resolves this split's `src/` and root package identity.
- Added behaviour at existing `tests/public-contract.php` seam: retired models/new subscription label absent; all eight mail subtype/data behaviours retained; historical opaque fields and labels preserved. No assertion removed or weakened.
- Red against unchanged split-base `src/`, using `php tests/public-contract.php`: exit 255, `RuntimeException: Retired calendar model is absent: CalendarWebhookEventData`.
- Reapplied exact immutable source patch; same command: exit 0, `Qubrics Management public contract passed`.
- `git diff --check`: exit 0. Metadata/history/workflow diff: empty.

Raw receipts: `/Users/rj/Desktop/GIT-REPOS/sendmux-php-management/.claude/artifacts/sdk344-calendar-source-20261120/` (`canonical.patch`, `composer-validate.log`, `php-syntax.log`, `composer-install-offline.log`, `public-contract-red.log`, `public-contract-green.log`). Exact source equality receipt: `/Users/rj/Desktop/GIT-REPOS/sendmux-php-mailbox/.claude/artifacts/sdk344-calendar-source-20261120/exact-source-equality.log` (both PHP splits).

## Completion state

Correctness: Immutable generated-source delta only. Active calendar surface removed without losing historical webhook data or typed mail payloads. This is a major API removal in source and must receive a separate release decision.

Journeys: No live or browser journey; no server/UI change. Unchanged PR CI installs and exercises the actual split on PHP 8.2.

Status: Source PR only. Merge, publication and live calendar backend retirement remain outside this task.

Rollback: Normal revert of the source-sync commit; preserved archive unchanged.

Torn down: No long-lived process or external resource created. Worktree retained for PR review.

Parked: No `check:fast` script exists; existing native metadata, syntax and contract checks compose this task's fast gate. Live retirement waits on `infra-migration1f`; published package bytes unchanged.
