=== Sticky Call CTA ===
Contributors:      michaelkanda
Tags:              call, sticky, mobile, cta, analytics
Requires at least: 6.5
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        1.2.1
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Sticky mobile call button with per-location phone numbers, page or URL based assignment and a GA4 event on click.

== Description ==

Displays a fixed call button at the bottom of the screen on mobile viewport
widths. The admin interface of this plugin is in German.

Phone numbers are maintained as a central list of locations. Every page can
pick one of those locations, and URL rules with a wildcard can assign numbers
automatically, so new landing pages below a given path get the correct number
without any manual step.

Assignment order:

1. the selection in the meta box of the page
2. the first matching URL rule
3. the default location

Visibility is handled entirely in CSS, not by server side device detection.
The markup is therefore identical for every device and works with full page
caching.

= Tracking =

On click the plugin sends a GA4 event, by default `phone_call_click` with the
parameters `location_label`, `location_id`, `phone_number`, `page_path` and
`link_url`. The recommended event `generate_lead` can be sent in addition.

The plugin does not contact any external service by itself. It hands the data
to the Google tag already present on the site, either `gtag()` as provided by
Site Kit or gtag.js, or the `dataLayer` for Google Tag Manager. Whether and how
that data reaches Google is decided by the existing analytics configuration of
the site. The event can optionally be made dependent on the JavaScript variable
`window.dsgnSccConsentGranted`.

= Filtering automated clicks =

Before sending, the plugin optionally checks whether the click plausibly comes
from a human: `event.isTrusted`, a preceding real input such as a tap, scroll
or key press, `navigator.webdriver` and a minimum dwell time. In addition only
one event per page view is sent. The tel: link always works regardless of these
checks.

= Built in click counter =

Besides the GA4 event the plugin counts clicks in its own database table,
aggregated by location, day and page ID. No cookies are set and no IP addresses
or visitor identifiers are stored. The numbers are shown in the location list
as the sum of the last 30 days and as a total. A daily task removes rows older
than 400 days.

Because visitors without analytics consent are counted as well, these numbers
are usually higher than the ones in GA4.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP file
   via Plugins > Add New.
2. Activate the plugin.
3. Add at least one location under Settings > Sticky Call CTA.

== Frequently Asked Questions ==

= Why is the button not visible on desktop? =

It is only shown up to the configured breakpoint, 768 px by default.

= The number is wrong on one page =

Check the meta box of that page first, then the URL rules. The first matching
rule wins, and the selection on the page takes precedence over every rule.

= Why is an event missing although I clicked? =

With the automated click filter enabled, a click is discarded when it happens
faster than the configured minimum dwell time or without a preceding real
input. Set the minimum dwell time to 0 while testing.

= Why do the clicks in the location list differ from GA4? =

The built in counter does not depend on consent and therefore also counts
visitors who decline analytics. GA4 additionally filters its own list of known
bots.

= The button covers the cookie banner =

Set the z-index in the settings below the value used by the consent layer.

== Changelog ==

= 1.2.1 =
* Table names in all queries now use the %i placeholder of $wpdb->prepare().
* Removed Domain Path header and load_plugin_textdomain().
* readme.txt rewritten in English, minimum WordPress version raised to 6.5.

= 1.2.0 =
* Aggregated click counter per location, shown in the location list.
* REST endpoint dsgn-scc/v1/click with rate limiting.
* Daily cleanup task for old counter rows.

= 1.1.0 =
* Check for genuine user interaction before sending the GA4 event.
* Minimum dwell time and one event per page view.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.2.1 =
Requires WordPress 6.5 or newer.

= 1.2.0 =
Creates a database table for the click counter.

= 1.1.0 =
New options for filtering automated clicks.

= 1.0.0 =
First release.
