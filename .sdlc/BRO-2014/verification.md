# Verification Report: BRO-2014

**Issue:** BRO-2014, Gravity Forms auto-responder: Marketing opt-in field on each feed (ships in 1.3.0)
**Date:** 2026-11-13 (second run, after PLUGINS Manager's second-reader FAIL at 0de5bf8)
**Branch:** develop

## Executive Summary

| Check | Result |
|---|---|
| Claimed-commit integrity | PASSED |
| Second-reader findings 1 to 6 | All reproduced on 0de5bf8 before the fix; all fixed in a0177e3 |
| Code review | PASS after fix (independent review of the fix: 0 critical, 3 warnings and 2 suggestions, all fixed before commit) |
| Evidence can fail (mutation) | PASSED (10 unit mutations: 9 caught at first, 1 inert test found and fixed; probe positive control fails) |
| Acceptance review | PASS (findings 1 to 6 and the earlier contract) |
| Browser/UI gate | Not applicable: PHP only; checked through Gravity Forms in wp-env |
| Security | PASS_WITH_NOTES, see `security-scan.md` |
| Open decision | Prechecked limitation (below) for PLUGINS Manager |
| **Overall** | **READY** for 1.3.0, subject to that decision |

## Commit Range Covered

**Commit range covered:** `2a4fec0..fb7ddda`

| SHA | Subject |
|---|---|
| 2a4fec0 | BRO-2014: Marketing opt-in field on each feed |
| b6c0a81 | BRO-2014: pair a Multiple Choice field's ticked box with its own words |
| a0177e3 | BRO-2014: take the marketing opt-in answer at submission and find the box by its value |
| f238c49 | BRO-2014: say that a box ticked by custom code or script cannot be detected |
| fb7ddda | BRO-2014: refuse Select All, note changed words, render on a copy |

```bash
git fetch --all
git log --format='%h %s' origin/develop | grep -E '^[0-9a-f]+ BRO-2014:'
```

Anything else that prints landed after this run. Evidence commits (`SDLC evidence: ...`) deliberately do not match.

## Claimed-Commit Integrity

| SHA | Result |
|---|---|
| 2a4fec0, b6c0a81 | OK: develop, origin/develop, mirror/develop |
| a0177e3 | OK: develop, origin/develop (mirror updates on its own schedule) |

Spot-check: `resave_error`, `snapshot_marketing_opt_in` and the changelog words "whether it started ticked" are on develop.

## Second-Reader Findings (PLUGINS Manager, FAIL at 0de5bf8)

Each was reproduced on 0de5bf8 in wp-env (Gravity Forms 2.10.2) before the fix; the table is on BRO-2014.

| # | Finding | Fix (a0177e3) | Evidence |
|---|---|---|---|
| 1 | Input ids renumbered by position; feed kept the old id | The chosen box's value and text are saved with the feed (`save_feed_settings`); the answer is found by value. A removed box sends nothing and adds an entry note; the feed page says when the box moved or went; saving the old id onto another box is refused | Unit: moved, removed, ambiguous, resave; probe S1, F1 x5 |
| 2 | Wording read when the feed runs, not when the visitor submitted | Snapshot on `gform_entry_created` (after the spam flag, before feeds) in entry meta, per feed; the feed sends only the snapshot; no snapshot sends nothing | Unit; probe F2a (spam, relabel, Not spam sends the original words), F2b, F2 (API entry with no snapshot) |
| 3 | Prechecked missed boxes ticked by population or pre_render | Prechecked is true when the field allows dynamic population or the rendered choice (`GFFormDisplay::gform_pre_render( $form, 'form_config' )`, GF 2.9+) is preselected | Unit; probe F2b/F3, F3 |
| 4 | A field hidden by conditional logic sent false | Hidden (`GFFormsModel::is_field_hidden`) sends nothing. Also Administrative or Hidden visibility (found in review) | Unit; probe F4, V1 |
| 5 | Product option, survey, quiz, poll checkboxes accepted | Only `checkbox`, `multi_choice` (checkbox input) and `consent`; refused on save; the field list is limited too (`field_types`) | Unit; probe F5 |
| 6 | `plain()` mangled a literal "<" | Only real tags, comments and declarations are removed | Unit: "<3", "a < b > c" |

Also asked: the changelog says "whether it started ticked" (done); a test on real `GF_Field` objects (the probe, committed at `tests/probes/marketing-opt-in-probe.php`); consent `.2` tampering is refused by Gravity Forms (probe C2).

### Open decision: prechecked limitation

Captured server-side at submission from what Gravity Forms 2.9+ offers outside display (`gform_pre_render` in `form_config` context) plus the field's dynamic-population setting. Not seen: a `gform_pre_render` that depends on display-only state (the query string through `$field_values`, cookies, `$ajax`), or any change made in the browser. "Value arriving matches the pre-render state" is approximated, not exact. Calling `gform_pre_render` at submission also runs third-party callbacks where they did not run before; Gravity Forms itself does this for its own config. For PLUGINS Manager to decide.

## Code Review

Independent review of the fix (a separate agent given the diff and the brief, not this session's conclusions). It checked the GF API use against the 2.10.2 source. All points were fixed before commit, each with a test that fails on the earlier version:

1. **Warning, fixed:** a field with Administrative or Hidden visibility, set to start ticked, stores a tick nobody saw (GF `get_field_value` fills an administrative field's default, forms_model.php:3678; probe V1 shows the tick stored). Now refused on save and treated as hidden.
2. **Warning, fixed:** a feed with no saved box (made by the API) could never be saved again with the same box. Now let through; the save records the box (`resave_error`).
3. **Warning, fixed:** the notice said "no opt-in is sent" when the box had only moved, but it is still sent by value. The notice now says which.
4. **Suggestion, fixed:** the wording was cleaned twice, decoding entities shown literally. `payload_fields` now only cuts.
5. **Suggestion, fixed:** `gform_pre_render`'s per-request cache is flushed before and after the call.

Open: `snapshot_marketing_opt_in`, `marketing_opt_in_payload` and `save_feed_settings` are covered by the wp-env probe, not the unit suite (Gravity Forms is not loaded there).

## Can the Evidence Fail? (Phase 2.5)

Mutations of `class-marketing-opt-in.php`, `MarketingOptInTest` run each time. Control: OK (26 tests at the time).

| Mutation | Caught by |
|---|---|
| N0 payload always empty (positive control) | 16 tests |
| N1 tick read at the saved input id (old behaviour) | moved box; whole field |
| N2 dynamic population ignored | may have started ticked |
| N3 conditional logic ignored | hidden by conditional logic |
| N4 any checkbox type accepted | product, survey, quiz, poll refused |
| N5 old tag stripping | literal "<" kept |
| N6 rendered fields ignored | words rendered by a filter; started ticked |
| N7 pre_render tick ignored | keyed choice; started ticked |
| N8 snapshot without "ok" state accepted | **survived at first: inert.** The no-snapshot test passed no snapshot with an answer. Fixed by testing every non-ok state with an answer; re-run fails |
| N9 value match skipped when not found | removed or ambiguous box |

Survivors under N0, one line each: unmapped, no snapshot, consent text input, product refused, other field type and removed box all expect nothing (legitimate negative controls); literal "<" and entry reference test other methods; hidden expects nothing; rendered words asserts the snapshot directly.

Probe positive control: with the `gform_entry_created` hook removed, the probe fails at B1 (no opt-in sent).

The four tests added after review fail on the pre-review class and pass after.

No permission or cross-tenant tests; no comparison claim.

## Acceptance Review

| Requirement | Evidence | Result |
|---|---|---|
| Findings 1 to 6 (above) | Unit and probe | Met |
| `marketing_opt_in`, `_wording` (max 255), `_prechecked`, `_reference` (max 500) | Unit; probe | Met |
| Unmapped: none of the fields | Unit | Met |
| Never a 422 that loses the enquiry | Caps; nothing sent without words; the message itself is still sent when the opt-in is not (probe F1, F2, F4, V1) | Met |
| No default tick or legal wording from the plugin | Code read | Met |
| Readme, tooltip, changelog | Updated in a0177e3, changelog under Unreleased | Met |

Tests changed: `MarketingOptInTest` (written in this issue by this session) was rewritten for the snapshot API; all earlier cases are kept.

## Test Runs

- Unit: `vendor/bin/phpunit -c tests/phpunit-unit.xml.dist`: OK (173 tests, 326 assertions)
- Lint: `composer phpcs`, `composer compat:7.4`, `composer compat:8.4`: exit 0
- wp-env probe, Gravity Forms 2.10.2, real `GF_Field_Checkbox` objects, HTTP faked, fixture number +447700900123 (Ofcom drama range): 23/23 PASS
- CI on a0177e3: run 43462427508 success (PHPUnit wp-env; PHPCS, version consistency and Plugin Check)

## Third Run (2026-11-13): second reader PASS_WITH_NOTES through f238c49

PLUGINS Manager accepted prechecked as approximate (f238c49 adds the "cannot tell" wording). The second reader's pre-release notes, fixed in fb7ddda, each reproduced first:

| # | Note | Fix | Fails before the fix |
|---|---|---|---|
| 1 | `gform_pre_render` changed the submitted form's field objects in place | Rendered on a copy (`Marketing_Opt_In::form_copy`, fields cloned) | Unit test (error: no copy); probe D1 on real `GF_Field`: notifications/feeds saw "Envoyez-moi des offres" without the copy |
| 2 | Notice compared only the value | Words changed with the same value: notice says the answer is still sent; re-saving is allowed and records the new words; markup-only change is not noticed | Unit test failed |
| 3 | Select All bundles consent | Refused on save; snapshot state `bundled` sends nothing; feed page notice | Unit test failed; probe A1 |
| 4 | Readme accuracy | Show Values, dynamic population always counted, mapping in force at submission (accepted as designed) | Copy |
| 5 | "Broadcaster cannot tell" | "This plugin cannot tell" in tooltip and FAQ; wp-env shows the tooltip | Copy |

The changelog line was corrected to match (moved or words changed: still sent and noticed; removed or value changed: nothing sent).

Test changed: `MarketingOptInTest` (own test) gained `BUNDLED` in the non-ok states loop.

Code review: this session only. PLUGINS Manager spot-checks the diff in place of a third full read.

Runs on fb7ddda:
- Unit: OK (176 tests, 339 assertions)
- Lint: phpcs, compat 7.4, compat 8.4 exit 0
- wp-env probe: 26/26 PASS
- CI: run 43464152363 success

**Overall:** READY, pending PLUGINS Manager's spot-check. Not tagged.

---
Verified by: AI Assistant
Date: 2026-11-13
