# Verification Report: BRO-2003 (plugin side)

**Issue:** BRO-2003, form auto-replies and abuse. Plugin side: P1 spam-marked entries, P2 feed copy,
and showing Broadcaster's reply template `warning` (ships in 1.3.0). The web side belongs to
broadcaster-web and is not covered here.
**Date:** 2026-11-13
**Branch:** develop

## Executive Summary

| Check | Result |
|---|---|
| Claimed-commit integrity | PASSED for this repo (one web SHA noted as an exception for BCAST) |
| Code review | PASS (0 critical, 2 warnings, 3 suggestions, none blocking) |
| Evidence can fail (mutation) | PASSED (6 mutations, all caught) |
| Acceptance review | PASS (P1, P2 and the warning: 3/3) |
| Browser/UI gate | Not applicable: PHP only; the warning and copy were checked rendered through Gravity Forms' Settings class in wp-env |
| Security | PASS_WITH_NOTES, see `security-scan.md` |
| **Overall** | **READY** for 1.3.0 |

## Commit Range Covered

**Commit range covered:** `bb66373..b6d6a90`

| SHA | Subject |
|---|---|
| bb66373 | BRO-2003: feed settings say who receives automatic replies |
| b6d6a90 | BRO-2003: show Broadcaster's reply template warning in feed setup |

This repo's subjects are `BRO-NNNN: ...`, so the match is on that prefix, not the skill's
conventional-commit scope regex (which matches nothing here):

```bash
git fetch --all
git log --format='%h %s' origin/develop | grep -E '^[0-9a-f]+ BRO-2003:'
```

No out-of-range commits.

## Claimed-Commit Integrity

| SHA | Repo | Result |
|---|---|---|
| bb66373 | this repo | OK: develop, origin/develop, mirror/develop |
| b6d6a90 | this repo | OK: develop, origin/develop, mirror/develop |
| ac1eb60d1, b3422c1d6 | broadcaster-web (plan anchors) | OK: on broadcaster-web origin/develop |
| a6405fcaf | broadcaster-web (the web change that sends `warning`) | **Exception, outside this repo:** resolves, but only on local `quality-bro2003*` branches; on no remote branch, and no BRO-2003 commit is on broadcaster-web origin/develop yet |

The exception does not block the plugin: an absent `warning` shows nothing (unit and wp-env tested),
so the plugin warning stays dormant until the web change ships. It is reported to BCAST via PLUGINS
Manager.

Spot-check: "An automatic reply goes to whatever number is typed into the form." is in the add-on
and pinned by `SpamGateTest`; the `broadcaster-template-warning` span is in
`class-reply-template-warning.php`, on develop.

## Code Review

Independent review (a separate agent given the diff, not this session's conclusions).

### Warnings (open, not blocking)

1. `class-broadcaster-gf-feedaddon.php` `reply_template_warning()`: opening a feed's edit page now
   asks Broadcaster about up to two templates (10 s timeout each, `class-client.php`). Before, that
   happened only on save. If Broadcaster is slow or down, the feed page can take up to about 20 s to
   open. Not measured. *Recommendation:* follow-up issue for a shorter render-path timeout or a
   short transient.
2. The one-check-per-request cache, the postback-or-saved name choice and the "different" mode rule
   are private and not unit-tested. They were checked in wp-env (save over a saved template: one
   request; off the feed edit page: nothing; out of hours with "same": nothing), recorded on BRO-2003.

### Suggestions (open)

3. The render path normalises the posted name with `sanitize_text_field( wp_unslash() )`, while
   save validation uses Gravity Forms' value. A name with tags, tabs or `%xx` would cost two calls.
   Unlikely with real template names.
4. `SpamGateTest`'s hook regex matches single-quoted hook names from a fixed list only. It is a
   tripwire, not proof; the proof is the wp-env run recorded on BRO-2003 (cases A to G).
5. `role="alert"` on text present at page load makes screen readers announce it each time;
   `role="status"` would be quieter.

No PHP 7.4 problems; the warning output is escaped and no XSS was found.

## Can the Evidence Fail? (Phase 2.5)

Mutations in a scratch copy, the warning, template check and client parsing tests run each time
(17 tests).

| Mutation | Caught by |
|---|---|
| W1 `esc_html` removed | a warning is shown in Broadcaster's words; markup from the API is shown as text |
| W2 warning shown on a refused template | a refused template shows its error, not a warning |
| W3 blank warning shown | no warning shows nothing |
| C1 client drops `warning` | a warning comes back word for word |
| C2 non-string `warning` passed through | no warning field or a null one is no warning |
| SpamGate: a `gform_after_submission` hook added (mutation run when written, 13 Nov) | the add-on leaves feed dispatch to Gravity Forms |

Survivors: for each mutation, the other tests in the run assert other properties (client
auto-response parsing, template yes/no/unknown, path encoding), each untouched by that mutation.
None asserts the mutated property, so none is inert. No permission tests; no comparison claim.

## Acceptance Review

| Requirement (BRO-2003 split, plugin side) | Evidence | Result |
|---|---|---|
| P1: a spam-marked entry never runs the feed | wp-env, GF 2.10.2: honeypot spam, `gform_entry_is_spam`, resend, trash and restore all 0 sends; "Not spam" by an admin 1 send; `SpamGateTest` | Met (no code change needed) |
| P2: feed settings say the reply goes to whatever number is typed, and recommend anti-spam | Section description rendered in wp-env; `SpamGateTest` copy assertions | Met |
| Show Broadcaster's template `warning`, escaped, only on the feed edit page | Unit and wp-env cases recorded on BRO-2003 | Met |
| Changelog under Unreleased | `* Changed:` spam copy line; `* Added:` warning line | Met |

Two existing assertions in `ReplyTemplateCheckTest` gained `'warning' => null` because the result
has a new key. Disclosed on BRO-2003 when made.

## Test Runs

- Unit: `vendor/bin/phpunit -c tests/phpunit-unit.xml.dist`: OK (161 tests, 259 assertions) at b6c0a81
- Lint: `composer phpcs`, `composer compat:7.4`, `composer compat:8.4`: exit 0
- CI on b6d6a90: run 43454550999 success

---
Verified by: AI Assistant
Date: 2026-11-13
