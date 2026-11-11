# Dependency Audit: 1.2.0

**Date:** 2026-11-11T14:18:31Z
**Commit:** 782b99a

| Tree | Policy | Exit | Result |
|---|---|---|---|
| plugin composer (no dev) | block | 0 | none shipped |
| root composer (tooling) | warn | 0 | clean |
| npm (dev env / build) | warn | 1 | advisories |

## plugin composer (no dev)
```
broadcaster-auto-responder-for-gravity-forms/composer.json requires no packages: the plugin ships no Composer dependencies.
```
## root composer
```
No security vulnerability advisories found.
```
## npm
```
### repository root (exit 1)
    Depends on vulnerable versions of @wp-playground/storage
    node_modules/@wp-playground/blueprints
      @wp-playground/cli  1.0.5 || 3.0.16 || 3.0.30 || >=3.1.17
      Depends on vulnerable versions of @wp-playground/blueprints
      Depends on vulnerable versions of @wp-playground/storage
      Depends on vulnerable versions of @wp-playground/tools
      node_modules/@wp-playground/cli
      @wp-playground/tools  >=3.1.18
      Depends on vulnerable versions of @wp-playground/blueprints
      node_modules/@wp-playground/tools

minimatch  7.0.0 - 11.0.0
Severity: moderate
minimatch has potential denial of service via globparts amplification - https://github.com/advisories/GHSA-g3hv-75wg-m249
fix available via `npm audit fix --force`
Will install @wordpress/env@12.1.0, which is a breaking change
node_modules/minimatch

proxy-addr  1.1.0 - 2.0.7
Severity: critical
proxy-addr vulnerable to IP spoofing via IPv4-mapped IPv6 trust subnet - https://github.com/advisories/GHSA-jqcg-44mw-7w3h
fix available via `npm audit fix`
node_modules/proxy-addr

qs  2.2.5 - 6.16.0
Severity: moderate
qs array-limit bypass via bracket-key comma parsing - https://github.com/advisories/GHSA-x5fp-wj9c-mxmx
qs: Denial of Service via Attacker Controlled isBuffer - https://github.com/advisories/GHSA-4mjr-xmp4-gh2g
qs : qs.stringify with arrayFormat 'comma' throws an uncaught TypeError on array elements carrying a non-callable own toString (DoS) - https://github.com/advisories/GHSA-6pq8-jhm2-2vcw
fix available via `npm audit fix`
node_modules/qs


18 vulnerabilities (1 low, 9 moderate, 5 high, 3 critical)

To address issues that do not require attention, run:
  npm audit fix

To address all issues (including breaking changes), run:
  npm audit fix --force

```
