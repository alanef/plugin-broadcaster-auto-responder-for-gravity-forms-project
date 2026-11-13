# Security Scan Report: BRO-2014

**Date:** 2026-11-13 (second run)
**Scanned by:** AI Assistant
**Files analyzed:** 2 code files (plus tests, probe, readme.txt and changelog.txt)

## Commit Range Covered

**Commit range covered:** `2a4fec0..a0177e3`

| SHA | Subject |
|---|---|
| 2a4fec0 | BRO-2014: Marketing opt-in field on each feed |
| b6c0a81 | BRO-2014: pair a Multiple Choice field's ticked box with its own words |
| a0177e3 | BRO-2014: take the marketing opt-in answer at submission and find the box by its value |

```bash
git fetch --all
git log --format='%h %s' origin/develop | grep -E '^[0-9a-f]+ BRO-2014:'
```

## Summary

| Severity | Count |
|----------|-------|
| Critical | 0 |
| High | 0 |
| Medium | 0 |
| Low | 1 |
| Info | 4 |

**Recommendation:** PASS_WITH_NOTES

The earlier Low (wording read when the feed runs) is closed by a0177e3: the answer is taken at submission.

## Findings

### Low

1. **A04: Insecure Design**, `class-broadcaster-gf-feedaddon.php` `rendered_fields()`: third-party
   `gform_pre_render` callbacks now also run at submission, in Gravity Forms' own `form_config`
   context. Gravity Forms does the same for its config. A callback that assumes display could
   misbehave. Prechecked is approximate (see verification.md, open decision).

### Info

1. **A03: Injection**: the feed page notice, the validation error and the entry note are escaped
   (`esc_html`, `esc_html__`). Wording is sent as JSON. The only visitor-sent wording is the consent
   `.2`, which Gravity Forms refuses when changed (probe C2).
2. **A01: Access control**: the reference is a wp-admin URL from int-cast ids; the feed settings are
   under Gravity Forms' capability check; the snapshot is written only server-side at entry creation.
3. **Data protection**: the entry now keeps the snapshot (the tick, the words shown, prechecked) in
   entry meta. It is a copy of what the entry already records, deleted with the entry (Gravity Forms
   forms_model.php:2873). Log lines hold feed and entry ids and a state only.
4. **Consent integrity**: a box the visitor never saw (conditional logic, Administrative or Hidden
   visibility) sends nothing, so no consent is recorded for a box nobody saw (probe V1, F4).

## Instrument Validation

| Class | Instrument | Control | Result |
|---|---|---|---|
| Output escaping | Code read; phpcs `phpcs_sec.xml` | Known from BRO-2003 W1: phpcs does not flag removed escaping in a returned string | phpcs exit 0 here is not evidence; the escaping was checked by reading each output |
| Secret exposure | Grep of added lines for `api_key|BROADCASTER_API_KEY|token|secret|password|Authorization|Bearer` | Validated against 66cfd84^ (BRO-1468) | 0 hits in range |
| Personal data in logs | Read of each new `log_*` call | No earlier fix of this class in the repo | UNVALIDATED instrument; 2 new lines, ids and state only |
| Consent for an unseen box | Unit test and probe V1 | Fails on the pre-review class | Valid |

## Checklist Status

- [x] OWASP Top 10 reviewed
- [x] Secret exposure checked
- [x] Input validation reviewed (mapping refused for other field types and unseen fields)
- [x] Dependencies checked (no Composer or npm changes)
- [x] Auth/authz reviewed
- [x] Data protection reviewed (entry meta, deleted with the entry)

## Next Steps

None blocking. PLUGINS Manager to decide on the prechecked limitation.
