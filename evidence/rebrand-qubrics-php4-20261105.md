# PHP Management Qubrics rename

Goal: ship `qubrics/management` at the next major with `Qubrics\Management` and qubrics.com defaults, per qubrics/qubrics-php-sdk#4 and A11.

Status: coded, not merged. Candidate version `3.0.0` derives from latest remote tag `v2.1.1`; tags need exact-version Ops approval. No publication or Packagist write.

## Correctness

- `composer.json` renames the package, PSR-4 prefix and core dependency together; `qubrics/core:^3.0` preserves existing third-party constraints.
- All generated PHP namespace/import/type strings use `Qubrics`; Configuration default and server-list hosts use `https://app.qubrics.com/api/v1`, with package version `3.0.0`.
- Existing `smx_*` prefixes, OAuth factories, explicit base URLs, `sendmux_message_id`, `sendmux.test` and `X-Sendmux-*` protocol names remain compatible.
- README documents the package/import migration. Historical changelog and evidence are untouched.

Canonical references: [A11](https://github.com/qubrics/qubrics/blob/main/specs/rebrand-qubrics/spec.md), [Composer schema](https://getcomposer.org/doc/04-schema.md), [path repository versions](https://getcomposer.org/doc/05-repositories.md#path).

## Tests and checks

Added `tests/public-contract.php`, using real Composer autoload, factories, generated request builders and nested deserialisation without sending HTTP requests.

- Red: exit 255 before the rename, `RuntimeException: Composer must install qubrics/management`.
- Green: exit 0 after the rename, `Qubrics Management public contract passed`.
- Checks: `composer validate --strict`, PHP syntax for every source/test file and `git diff --check`, all exit 0.
- CI installs the checked-out core candidate via a temporary Composer manifest with path version `3.0.0`, then runs the public contract. Original Composer metadata is validated before the temporary manifest is generated.
- Local environment: PHP 8.4.10, Composer 2.8.9. CI uses PHP 8.2.
- Initial strict validation rejected the untracked baseline install's old `composer.lock`. That scratch lock was moved into artifacts; shipped metadata has no lockfile. No assertion or dependency constraint was weakened.
- Review found a blanket brand substitution renamed the accessors for unchanged `sendmux_message_id`. A public deserialise/get/set/serialise test failed with `Call to undefined method Qubrics\Management\Model\WebhookEventData::getSendmuxMessageId()`. Restoring the original getter/setter and serializer maps removed the cause; the same test passes. This preserves the A17 legacy contract without adding new wire fields.

Logs: MAIN `.claude/artifacts/rebrand-qubrics-php4/management-{baseline-install,candidate-install,public-contract-red,public-contract-green,syntax}.log`; baseline lock retained there as `management-baseline-composer.lock`.

## Release gates

SDK monorepo main `2498ae8e90932fa98b54926653a4a6185bb2a4c3`, `scripts/publish-php-split.mjs:40-60`, requires canonical-source parity and still expects `sendmux/management`. Generator/source/publisher alignment needs its own owner before approved tagging; this work does not bypass the publisher.

Public Packagist installation and old-package abandonment remain unverified. Independent copy critique completed; explicit old/new exception migration added. One humanize pass on changed prose and protected-set check completed, preserving package names, namespace, versions, credentials and URLs. No browser journey applies. No background resources created.
