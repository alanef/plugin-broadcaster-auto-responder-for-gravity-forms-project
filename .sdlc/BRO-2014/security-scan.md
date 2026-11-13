# Security Scan Report: BRO-2014

**Date:** 2026-11-13
**Scanned by:** AI Assistant
**Files analyzed:** 4 code files (plus readme.txt and changelog.txt)

## Commit Range Covered

**Commit range covered:** `2a4fec0..b6c0a81`

| SHA | Subject |
|-----|---------|
| 2a4fec0 | BRO-2014: Marketing opt-in field on each feed |
| b6c0a81 | BRO-2014: pair a Multiple Choice field's ticked box with its own words |

**Check this yourself** (this repo's subjects are `BRO-NNNN: ...`, so the match is on that prefix):

```bash
git fetch --all
git log --format='%h %s' origin/develop | grep -E '^[0-9a-f]+ BRO-2014:'
```

No out-of-range commits.

## Summary

| Severity | Count |
|----------|-------|
| Critical | 0 |
| High | 0 |
| Medium | 0 |
| Low | 1 |
| Info | 3 |

**Recommendation:** PASS_WITH_NOTES

## Findings

### Low

1. **A04: Insecure Design (data integrity of consent proof)**, `includes/gf/class-marketing-opt-in.php`:
   checkbox wording is read from the form when the feed runs. A feed run later (entry marked "Not
   spam", delayed feed) sends the label current at that time. Consent fields use the entry's own
   `.2`. *Recommendation:* accept for 1.3.0; note it for Broadcaster's consent record.

### Info

1. **A03: Injection**: the only visitor-sent wording is the consent field's `.2`. Gravity Forms'
   state validation refuses a tampered hidden value; the wp-env probe saw a submission with a
   mismatched state refused. Checkbox wording comes from the form configuration (admin only).
   Wording is sent as JSON; Broadcaster must escape it wherever it shows it.
2. **A01: Access control**: the reference is a wp-admin URL, built from int-cast ids on
   `admin_url()`. Only a logged-in admin of that site can open it. The settings field and its
   validation run on the Gravity Forms feed page, under Gravity Forms' own capability check.
3. **Data protection**: new data sent to Broadcaster (answer, words, prechecked, entry link) is
   listed in the readme Privacy section and the Settings > Privacy text. The new log line
   (`process_feed`, unreadable mapping) holds feed, field and entry ids only, no personal data.
   Nothing new is stored.

## Instrument Validation

| Class | Instrument | Control | Result |
|---|---|---|---|
| Output escaping | Unit tests; phpcs `phpcs_sec.xml` | Mutation removing `esc_html` (on BRO-2003's warning, the only new escaped output) | Unit test failed (valid). **phpcs did not flag it** (exit 0): returned strings are outside EscapeOutput, so phpcs is not an instrument for this class |
| Secret exposure | Grep of added lines for `api_key|BROADCASTER_API_KEY|token|secret|password|Authorization|Bearer` | Last fix of the class, 66cfd84 (BRO-1468, saved key no longer sent to the browser): grep on `66cfd84^` finds the api_key field it fixed | Valid as a lower bound. BRO-2014's added lines: no hits |
| Personal data in logs | Read of every added `log_*` call | No previous fix of this class in the repo | **UNVALIDATED**: one added log line, read, ids only |
| Hidden input tampering | wp-env submission with a mismatched form state | Same probe, matching state saved | Valid: mismatched refused, matching saved |

## Checklist Status

- [x] OWASP Top 10 reviewed
- [x] Secret exposure checked (no keys, tokens or credentials added; fixture key in the throwaway probe only, not committed)
- [x] Input validation reviewed (mapping validated on save; values cut to Broadcaster's limits)
- [x] Dependencies checked (no Composer or npm changes)
- [x] Auth/authz reviewed
- [x] Data protection reviewed

## Next Steps

None blocking. Ships in 1.3.0.
