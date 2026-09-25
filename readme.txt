=== Simbe AI Website Care ===
Contributors: simbe1
Author: Simbe1
Author URI: https://profiles.wordpress.org/simbe1/
Tags: security, monitoring, vulnerabilities, site health, maintenance
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor plugins and themes for vulnerabilities, get email alerts, and check common issues. Built for freelancers and agencies managing many sites.

== Description ==

Simbe AI Website Care keeps your WordPress site healthy by monitoring your plugins and themes against WordPress.org data, flagging common configuration problems, and notifying you of new critical issues — all in one place.

**Plugin and theme security scan**

Every installed plugin and theme is checked against the WordPress.org directory and flagged when:

* It was **closed and removed from the directory** (often for security reasons)
* It is **no longer listed** (premium or custom items)
* An **update is available** and has not been applied
* It is **not tested** with your WordPress version
* It has **not been updated for a long time** (possibly abandoned)
* It **requires a newer PHP or WordPress version** than your site runs

**Email alerts**

Optionally set a notification email to receive a digest whenever a scan finds newly critical plugin or theme issues.

**Common issues checklist**

A guided checklist of the checks every site should pass, including debug mode, file permissions, HTTPS, PHP/database versions, auto-updates, backups, WP-Cron, memory limit, security headers and exposed files.

**Native Site Health integration**

Simbe Care adds its checks directly to Tools > Site Health, so you get one consolidated health report per site.

**Built for agencies**

Each site is checked in a single dashboard. Run scans manually or on a schedule, keep results cached, and review an at-a-glance risk summary. Ideal for freelancers and agencies that want a quick health overview before taking action on any client site.

**Why only WordPress.org data?**

This plugin deliberately uses only free, public WordPress.org data, so it works on any site with no API keys. For a full CVE database, pair it with a dedicated vulnerability scanner such as WPScan.

== Frequently Asked Questions ==

= Does this plugin use AI? =

Not in this version. The free, open-source release focuses on detecting plugin risk indicators and listing common issues without any external AI service. AI-assisted guidance is planned for a future premium release.

= Do I need an API key? =

No. All checks use free, public WordPress.org endpoints.

= How often are checks run? =

A background scan runs on the schedule you choose (daily or weekly). You can also run a scan at any time with the "Scan now" button. Results are cached between runs.

= Will this update or deactivate my plugins? =

No. Simbe Care is read-only. It reports risk indicators and issues, but never modifies plugins, themes or files. For guidance it points you to the relevant WordPress admin screens.

= Can it detect all CVEs? =

No. WordPress.org does not publish a full CVE database. Indicators here are directory status, update availability, activity and compatibility. Use a dedicated vulnerability database (e.g. WPScan) for complete CVE coverage.

= Can I get email alerts? =

Yes, optionally. In Simbe Care > Settings, add a notification email (separate multiple addresses with a comma). A digest is sent after a scan whenever new critical plugin or theme issues are found.

== Screenshots ==

1. The Simbe Care dashboard with the plugin and theme security scans and the common issues checklist.

== Changelog ==

= 1.2.0 =
* Security headers audit: the checklist now verifies HSTS, X-Content-Type-Options, frame protection (X-Frame-Options or CSP frame-ancestors), Referrer-Policy and Content-Security-Policy headers.
* Exposed files check: flags publicly readable debug.log files, database dumps (.sql/.sql.gz) and backup copies of wp-config.php.
* Tested up to WordPress 7.1.

= 1.1.0 =
* Added theme security scan: every installed theme is now checked against the WordPress.org directory (closed/removed, updates, tested versions, abandoned themes, PHP/WP requirements).
* Added optional email alerts: a digest is sent after a scan when new critical plugin or theme issues are found.

= 1.0.0 =
* Initial release.
* Plugin security scan against the WordPress.org directory (closed/removed, updates, tested versions, abandoned plugins, PHP/WP requirements).
* Common issues checklist (debug mode, HTTPS, permissions, auto-updates, backups, WP-Cron, memory and more).
* Native Site Health integration with seven additional checks.
* Single dashboard page with at-a-glance risk summary and scheduled background scans.

== Upgrade Notice ==

= 1.2.0 =
Adds a security headers audit, an exposed files check, and WordPress 7.1 compatibility.

= 1.1.0 =
Adds theme security scans and optional email alerts for new critical issues.

= 1.0.0 =
Initial release.
