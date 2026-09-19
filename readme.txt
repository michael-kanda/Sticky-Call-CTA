=== Sticky Call CTA ===
Contributors:      michaelkanda
Tags:              call, sticky, mobile, cta, analytics
Requires at least: 6.0
Tested up to:      6.8
Requires PHP:      7.4
Stable tag:        1.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Mobiler Sticky-Anrufbutton mit Telefonnummern je Standort, Zuordnung per Seite oder URL-Regel und GA4-Event beim Klick.

== Description ==

Zeigt auf mobilen Bildschirmbreiten einen fixierten Anrufbutton am unteren Rand.

Die Nummern werden zentral als Standorte gepflegt. Jede Seite kann einen dieser
Standorte auswaehlen, zusaetzlich lassen sich URL-Regeln mit Platzhalter
anlegen, sodass neue Landingpages in einem Pfad automatisch die richtige Nummer
erhalten.

Reihenfolge der Zuordnung:

1. Auswahl in der Metabox der Seite
2. erste passende URL-Regel
3. Standardstandort

Die Sichtbarkeit wird ausschliesslich ueber CSS gesteuert, nicht ueber
serverseitige Geraeteerkennung. Das Markup ist damit fuer alle Geraete gleich
und vertraegt sich mit Full-Page-Caching.

= Tracking =

Beim Klick wird ein GA4-Event gesendet, standardmaessig `phone_call_click` mit
den Parametern `location_label`, `location_id`, `phone_number`, `page_path` und
`link_url`. Optional wird zusaetzlich das empfohlene Event `generate_lead`
gemeldet.

Das Plugin sendet die Daten nicht selbst an einen externen Dienst, sondern
uebergibt sie an das bereits auf der Seite vorhandene Google-Tag
(`gtag()`, z. B. ueber Site Kit) beziehungsweise an den `dataLayer` fuer den
Google Tag Manager. Ob und wie diese Daten an Google uebertragen werden,
bestimmt die vorhandene Analytics-Konfiguration der Website. Optional kann das
Event von der Variable `window.dsgnSccConsentGranted` abhaengig gemacht werden.

= Bot-Filterung =

Vor dem Senden prueft das Plugin optional, ob der Klick plausibel von einem
Menschen stammt: `event.isTrusted`, eine vorausgegangene echte Eingabe
(Tippen, Scrollen, Tastendruck), `navigator.webdriver` sowie eine
Mindestverweildauer. Zusaetzlich wird pro Seitenaufruf nur ein Event gesendet.
Der tel:-Link funktioniert davon unabhaengig immer.

== Installation ==

1. Plugin-Ordner nach `/wp-content/plugins/` laden oder das ZIP ueber
   Plugins > Installieren hochladen.
2. Plugin aktivieren.
3. Unter Einstellungen > Sticky Call CTA mindestens einen Standort anlegen.

== Frequently Asked Questions ==

= Warum erscheint der Button am Desktop nicht? =

Er wird nur bis zum eingestellten Breakpoint angezeigt, standardmaessig 768 px.

= Die Nummer stimmt auf einer Seite nicht =

Zuerst die Metabox der Seite pruefen, danach die URL-Regeln. Die erste passende
Regel gewinnt, die Auswahl an der Seite hat Vorrang vor allen Regeln.

= Warum fehlt ein Event, obwohl ich geklickt habe? =

Bei aktiver Bot-Filterung wird ein Klick verworfen, der schneller als die
eingestellte Mindestverweildauer erfolgt oder ohne vorherige echte Eingabe
ausgeloest wurde. Zum Testen die Mindestverweildauer auf 0 setzen.

= Der Button verdeckt den Cookie-Banner =

Den z-index in den Einstellungen unter den Wert des Consent-Layers setzen.

== Changelog ==

= 1.1.0 =
* Pruefung auf echte Nutzerinteraktion vor dem Senden des GA4-Events.
* Mindestverweildauer und Entprellung pro Seitenaufruf.

= 1.0.0 =
* Erste Version.

== Upgrade Notice ==

= 1.1.0 =
Neue Optionen zur Bot-Filterung des GA4-Events.

= 1.0.0 =
Erste Version.
