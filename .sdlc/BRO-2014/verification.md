# Verification Report: BRO-2014

**Issue:** BRO-2014, Gravity Forms auto-responder: Marketing opt-in field on each feed (ships in 1.3.0)
**Date:** 2026-11-13
**Branch:** develop

## Executive Summary

| Check | Result |
|---|---|
| Claimed-commit integrity | PASSED |
| Code review | PASS after fix (1 critical found and fixed in b6c0a81; 0 open critical, 1 open warning, 4 suggestions) |
| Evidence can fail (mutation) | PASSED (12 mutations, all caught; 1 inert test found and fixed) |
| Acceptance review | PASS (10/10 requirements) |
| Browser/UI gate | Not applicable: PHP only, no templates or assets changed; settings field checked through Gravity Forms in wp-env |
| Security | PASS_WITH_NOTES, see `security-scan.md` |
| **Overall** | **READY** for 1.3.0 |

## Commit Range Covered

**Commit range covered:** `2a4fec0..b6c0a81`

| SHA | Subject |
|---|---|
| 2a4fec0 | BRO-2014: Marketing opt-in field on each feed |
| b6c0a81 | BRO-2014: pair a Multiple Choice field's ticked box with its own words |

This repo's commit subjects are `BRO-NNNN: ...`, not conventional-commit scopes, so the skill's regex
`[a-z]+\(...\):` matches nothing here. The match used is anchored on the subject prefix instead:

```bash
git fetch --all
git log --format='%h %s' origin/develop | grep -E '^[0-9a-f]+ BRO-2014:'
```

Anything that prints which is not in the table landed after this run. The evidence commit itself
(subject `SDLC evidence: ...`) is docs only and deliberately does not match.

## Claimed-Commit Integrity

`git fetch --all`, then `git cat-file -e` and `git branch -a --contains`:

| SHA | Repo | Result |
|---|---|---|
| 2a4fec0 | this repo | OK: develop, origin/develop, mirror/develop |
| 6b66f07ac | broadcaster-web (contract on BRO-2012, cited in the plan) | OK: on broadcaster-web origin/develop; not this repo's code |

Change-set is not empty (2a4fec0: 6 files, 458 insertions). Spot-check: the claimed setting label, the validation
message "more than one box" and the readme FAQ "What does the Marketing opt-in field do?" are all
found on develop with `git grep`.

## Code Review

Independent review (a separate agent given the diff, not this session's conclusions), with a wp-env
probe against Gravity Forms 2.10.2 using real `GF_Field` objects.

### Critical (fixed)

1. `includes/gf/class-marketing-opt-in.php` `target()`: paired `inputs[$i]` with `choices[$i]` by
   position. Gravity Forms 2.9+ Multiple Choice and Image Choice fields (inputType `checkbox`) link
   them by `key`, and moving a choice in the editor reorders choices but not inputs. Reproduced in a
   unit test before the fix: a tick on "Yes send offers" was sent with the words "No marketing
   please" and the wrong prechecked value. **Fixed in b6c0a81**: keyed inputs match by key (as
   `GF_Field::get_choice_id_from_input_key` does); a key with no matching choice is not a box.
   Checked in wp-env with a real `multi_choice` field stored as choices `[kb, ka]`, inputs `[ka, kb]`:
   5.1 ticked sends "Yes send offers", prechecked true; 5.2 ticked sends "No marketing please",
   prechecked false. The ten original probe cases (A to J) give identical results after the fix.

### Warning (open, not blocking)

2. Test coverage: `process_feed`'s log line for an unreadable mapping is not unit-tested (Gravity
   Forms is not loaded in the unit suite). It is exercised in the wp-env probe cases I and J.

### Suggestions (open)

3. Checkbox wording is read from the form when the feed runs, not when the form was submitted. A
   feed that runs later (an entry marked "Not spam", a payment-delayed feed) sends the label as it is
   then. Consent fields are unaffected: their wording comes from the entry's `.2`.
4. Without mbstring, `substr` may cut inside a UTF-8 character at 255. `wp_json_encode` repairs it,
   so no 422; the last character is lost.
5. Unit fixtures are arrays; real `GF_Field` objects (ArrayAccess) are covered only by the wp-env probe.
6. Whole-field mapping now also requires exactly one choice as well as one input (tightened in b6c0a81).

No PHP 7.4 problems. No path to a 422: wording is capped at 255 characters, the reference at 500, and
nothing is sent when there are no words or no mapping.

## Can the Evidence Fail? (Phase 2.5)

Mutations of `class-marketing-opt-in.php` in a scratch copy of the working tree, `MarketingOptInTest`
run each time. Unmutated control: OK (17 tests).

| Mutation | Caught by |
|---|---|
| M0 payload always empty (positive control) | 12 tests |
| M1 checkbox tick inverted | 6 tests |
| M2 consent never ticked | ticked consent sends the text the visitor saw |
| M3 prechecked always false | box set to start ticked says so |
| M4 wording not cut | wording cut to 255 characters |
| M5 whole field with any number of boxes | whole field only when one box |
| M6 choice index off by one | second choice; choice past the ninth |
| M7 consent wording always the label | ticked consent sends the text the visitor saw |
| M8 no label fallback | box with no words falls back to label |
| M9 any consent input is the box | consent Text or Description input is not the box |
| M10 blank wording still sent | box with no words sends nothing |
| M11 markup kept | markup in the label is sent as the words read |
| Keyed choices paired by position (the critical above) | keyed choice reordered in the editor |

Survivors under M0, one line each:
- Unmapped sends none: legitimate negative control, expects nothing.
- Consent Text or Description input is not the box: legitimate, expects nothing plus a mapping error.
- Another field type sends nothing: legitimate, expects nothing plus a mapping error.
- Entry reference is the admin URL: legitimate, tests a different method.
- No reference leaves the key out: **could not detect "send nothing"** (asserting a key is absent
  passes on an empty array). Finding, fixed in b6c0a81: the test now also asserts the answer is
  sent. Re-run of M0: it fails.

Survivors under M1 to M11 are the tests of the other properties, each untouched by that one mutation.
No permission or cross-tenant tests in this change; no comparison claim is made.

## Acceptance Review

| Requirement (BRO-2014 contract, 11 and 12 Nov; BRO-2012 published contract) | Evidence | Result |
|---|---|---|
| Feed setting "Marketing opt-in field", optional, checkbox or consent | `field_select` with `input_types` checkbox, consent; probe choice list | Met |
| `marketing_opt_in` true when ticked, false when not | Probe A to G, K to M; unit tests | Met |
| `marketing_opt_in_wording`: the words seen, at most 255 | Probe A (markup stripped), E (entry `.2`); unit 255-char multibyte test | Met |
| `marketing_opt_in_prechecked` | Probe C, D, K, M | Met |
| `marketing_opt_in_reference`: entry admin URL, at most 500 | Probe ref `admin.php?page=gf_entries&view=entry&id={FORM}&lid={ENTRY}`; unit 500 test | Met |
| Unmapped: none of the fields sent | Probe H; unit unmapped test | Met |
| Never a 422 that loses the enquiry | Caps, and no wording means nothing sent (unit) | Met |
| No default tick or legal wording from the plugin | Code read: no defaults; tooltip has no legal text | Met |
| Readme and settings help explain it | readme Configure step 5, FAQ, Privacy; tooltip; Settings > Privacy text | Met |
| Changelog under Unreleased | `changelog.txt` Added line | Met |

Order of release: live broadcaster-web v2.5.1 passes on only validated fields
(`IncomingMessageController::store`, `$request->validate([...])`, read at tag v2.5.1), so the new
keys are dropped, not refused, until BRO-2012 is live. 1.3.0 does not depend on it. This was read in
the source, not sent to the live API (a live send would create a real message).

## Test Runs

- Unit: `vendor/bin/phpunit -c tests/phpunit-unit.xml.dist`: OK (161 tests, 259 assertions)
- Lint: `composer phpcs`, `composer compat:7.4`, `composer compat:8.4`: exit 0
- wp-env, Gravity Forms 2.10.2, HTTP faked, fixture identity (+447700900123, Ofcom drama range):
  cases A to M as above
- CI on 2a4fec0: run 43456100131 success; CI on b6c0a81: see the push of this evidence

---
Verified by: AI Assistant
Date: 2026-11-13
