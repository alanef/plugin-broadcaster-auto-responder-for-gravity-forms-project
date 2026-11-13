# Security Scan Report: BRO-2003 (plugin side)

**Date:** 2026-11-13
**Scanned by:** AI Assistant
**Files analyzed:** 3 code files (plus tests, readme.txt and changelog.txt)

## Commit Range Covered

**Commit range covered:** `bb66373..b6d6a90`

| SHA | Subject |
|-----|---------|
| bb66373 | BRO-2003: feed settings say who receives automatic replies |
| b6d6a90 | BRO-2003: show Broadcaster's reply template warning in feed setup |

**Check this yourself:**

```bash
git fetch --all
git log --format='%h %s' origin/develop | grep -E '^[0-9a-f]+ BRO-2003:'
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

1. **Availability**, `includes/gf/class-broadcaster-gf-feedaddon.php` `reply_template_warning()`:
   the feed edit page now waits on up to two Broadcaster requests (10 s timeout each). Admin page
   only; no data exposure. *Recommendation:* follow-up for a shorter render-path timeout.

### Info

1. **A03: Injection (XSS)**: the `warning` text from the API is escaped with `esc_html` inside the
   plugin's own span. Markup from the API is shown as text (unit test, and wp-env with `<b>` and
   `<script>`).
2. **A10: SSRF / request scope**: the template check goes only to the configured Broadcaster API URL
   (admin setting, unchanged), with the template name encoded into the path (unit test). It runs only
   on the feed edit page, under Gravity Forms' capability check.
3. **Abuse (P1)**: spam-marked entries never run the feed; the plugin adds no dispatch path around
   Gravity Forms' spam gate (wp-env cases, `SpamGateTest`).

## Instrument Validation

| Class | Instrument | Control | Result |
|---|---|---|---|
| Output escaping | Unit tests; phpcs `phpcs_sec.xml` | Mutation W1 removing `esc_html` | Unit tests failed (valid). **phpcs did not flag it** (exit 0), so phpcs is not an instrument for escaping in returned strings |
| Secret exposure | Grep of added lines for `api_key|BROADCASTER_API_KEY|token|secret|password|Authorization|Bearer` | Last fix of the class, 66cfd84 (BRO-1468): grep on `66cfd84^` finds the api_key field it fixed | Valid as a lower bound. Hits in this range: 3, all passing the key to `Client` for the existing Bearer header; none outputs or logs it |
| Feed dispatch around the spam gate | `SpamGateTest` | Mutation adding a `gform_after_submission` hook | Test failed (valid); a tripwire for known hooks only |

## Checklist Status

- [x] OWASP Top 10 reviewed
- [x] Secret exposure checked
- [x] Input validation reviewed (posted template name sanitised; API text escaped)
- [x] Dependencies checked (no Composer or npm changes)
- [x] Auth/authz reviewed
- [x] Data protection reviewed (no new data sent or stored; no new log lines)

## Next Steps

None blocking. Ships in 1.3.0.
