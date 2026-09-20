# Sticky Call CTA

Mobiler Sticky-Anrufbutton für WordPress. Telefonnummern werden zentral als
Standorte gepflegt und per Seite oder URL-Regel zugeordnet. Klicks werden als
GA4-Event gemeldet und zusätzlich intern aggregiert gezählt.

Autor: Michael Kanda, [designare.at](https://designare.at/)
Lizenz: GPL-2.0-or-later

> Die `readme.txt` ist bewusst auf Englisch, weil das WordPress-Verzeichnis
> Englisch als Basissprache verlangt. Diese Datei hier ist die deutsche
> Arbeitsdokumentation und wird vom Verzeichnis nicht ausgewertet.

## Anforderungen

- WordPress 6.5 oder neuer (wegen des `%i`-Platzhalters in `$wpdb->prepare()`)
- PHP 7.4 oder neuer

## Installation

1. ZIP über Plugins → Installieren hochladen oder den Ordner nach
   `wp-content/plugins/` kopieren.
2. Aktivieren. Dabei wird die Tabelle `wp_dsgn_scc_clicks` angelegt.
3. Unter Einstellungen → Sticky Call CTA mindestens einen Standort anlegen.

## Zuordnung der Nummern

Es gewinnt die erste Regel, die zutrifft:

1. Auswahl in der Metabox der Seite
2. erste passende URL-Regel
3. Standardstandort

Sowohl an der Seite als auch in einer Regel lässt sich „Button ausblenden“
wählen. URL-Muster werden gegen den Pfad geprüft, `*` steht für beliebige
Zeichen, etwa `/wien/*` oder `/standort-graz-*`. Neue Landingpages unter einem
passenden Pfad bekommen die Nummer damit ohne manuellen Schritt.

Nummern dürfen frei formatiert eingegeben werden. Für den `tel:`-Link wird
normalisiert: `+43…` bleibt, `0043…` wird zu `+43…`, eine führende `0` wird
über die eingestellte Landesvorwahl ersetzt.

## Mobile Erkennung

Die Sichtbarkeit steuert ausschließlich eine Media Query, die aus dem
eingestellten Breakpoint inline erzeugt wird. Bewusst **keine**
serverseitige Geräteerkennung wie `wp_is_mobile()`, weil das Markup sonst im
Full-Page-Cache von Raidboxes dem falschen Gerätetyp ausgeliefert würde.

## Tracking

Beim Klick wird `phone_call_click` gesendet, mit den Parametern
`location_label`, `location_id`, `phone_number`, `page_path` und `link_url`.
Optional zusätzlich `generate_lead`.

Der Versand läuft über das bereits vorhandene Google-Tag: bevorzugt `gtag()`
(bei Site Kit vorhanden), sonst `dataLayer.push()` für den Tag Manager. Das
Plugin selbst kontaktiert keinen externen Dienst.

In GA4 müssen die Parameter einmalig als benutzerdefinierte Dimensionen
angelegt werden, sonst ist nur der Eventname auswertbar.

### Consent

Ohne Google Consent Mode lässt sich das Event an eine Variable binden: Option
aktivieren und das Cookie-Tool bei Einwilligung
`window.dsgnSccConsentGranted = true` setzen lassen. Der Anruf selbst
funktioniert davon unabhängig immer.

### Filterung automatisierter Klicks

Vor dem Senden wird geprüft: `event.isTrusted`, eine vorausgegangene echte
Eingabe (Tippen, Scrollen, Tastendruck), `navigator.webdriver` und eine
Mindestverweildauer. Dazu wird pro Seitenaufruf nur ein Event gesendet.
Tastaturbedienung bleibt absichtlich zählbar.

Fehlt beim Testen ein Event, ist meist die Mindestverweildauer schuld — zum
Testen auf 0 setzen.

## Eigene Klickzählung

Gespeichert wird aggregiert nach Standort, Tag und Seiten-ID. Kein Cookie,
keine IP, keine Besucherkennung — deshalb unabhängig vom Consent-Status. Die
Zahlen stehen in der Standortliste als Summe der letzten 30 Tage und gesamt.

Die Werte liegen systematisch über denen in GA4, weil auch Besucher ohne
Analytics-Einwilligung gezählt werden. Nicht 1:1 vergleichen.

Der Endpunkt `POST /wp-json/dsgn-scc/v1/click` ist öffentlich, weil eine Nonce
im Full-Page-Cache mit ausgeliefert würde und nach Ablauf Zählungen verlöre. Er
erhöht nur einen Zähler, liest nichts aus und prüft die Standort-ID. Die
Ratenbegrenzung nutzt einen gesalzenen Hash der IP in einem Transient; die
Adresse selbst wird nicht gespeichert.

## Eigenes CSS

Feld unter Einstellungen → Darstellung. Wird nach dem Plugin-Stylesheet
ausgegeben und nur geladen, wenn der Button auf der Seite erscheint.

Variablen: `--dsgn-scc-font-size`, `--dsgn-scc-meta-font-size`,
`--dsgn-scc-bg`, `--dsgn-scc-fg`, `--dsgn-scc-z`, `--dsgn-scc-height`.

Klassen: `.dsgn-scc`, `.dsgn-scc__link`, `.dsgn-scc__icon`, `.dsgn-scc__label`,
`.dsgn-scc__meta`, `.dsgn-scc__location`, `.dsgn-scc__number`.

Speichern setzt die Berechtigung `unfiltered_html` voraus. HTML-Tags werden
entfernt, unausgeglichene geschweifte Klammern führen zur Ablehnung mit
Hinweis — der bisherige Wert bleibt dann erhalten.

Die Ausgabe steht bewusst außerhalb der Media Query, damit sich auch der
Breakpoint überschreiben lässt.

## Call Tracking / dynamische Rufnummern

Der Button ist für Skripte zur dynamischen Rufnummernzuweisung vorbereitet:

| Ansatzpunkt | Bedeutung |
| --- | --- |
| `a.dsgn-scc__link[data-dsgn-scc-tel]` | der Link, `href` und Attribut enthalten die hinterlegte Nummer |
| `[data-dsgn-scc-swap="number"]` | die sichtbare Nummer, in einem eigenen Element ohne Standortnamen |
| `data-dsgn-scc-location` | Standort-ID, falls der Anbieter je Standort einen eigenen Pool nutzt |

Tauscht ein Skript `href` oder die sichtbare Nummer, zieht das Plugin das
`aria-label` nach und meldet an GA4 die tatsächlich verlinkte Nummer, nicht die
in der Datenbank hinterlegte.

**Statisches Call Tracking** braucht keinen Code: die Weiterleitungsnummer
einfach als Nummer des Standorts eintragen.

**Nicht vergessen:** Echte Nummer in Impressum, Schema-Markup und Google-
Unternehmensprofil belassen, sonst leidet die NAP-Konsistenz. Und: Eine
Nummer je Besucher ist ein Personenbezug und damit einwilligungspflichtig,
eine feste Nummer je Standort oder Landingpage nicht.

## Filter für Entwickler

| Filter | Zweck | Standard |
| --- | --- | --- |
| `dsgn_scc_settings` | Einstellungen zur Laufzeit überschreiben | – |
| `dsgn_scc_resolved_location` | ermittelten Standort überschreiben | – |
| `dsgn_scc_supported_post_types` | Post-Types mit Metabox | öffentliche Typen ohne Anhänge |
| `dsgn_scc_rate_limit` | Zählvorgänge pro 5 Minuten, 0 = aus | `10` |
| `dsgn_scc_retention_days` | Aufbewahrung der Zählerzeilen | `400` |

## Datenbank

Tabelle `{prefix}dsgn_scc_clicks`: `id`, `location_id`, `stat_day`, `post_id`,
`clicks`, eindeutiger Schlüssel über die mittleren drei Spalten. Die Seiten-ID
wird bereits mitgeschrieben, damit sich eine Auswertung je Landingpage später
ohne neue Datensammlung nachrüsten lässt.

Beim Löschen des Plugins wird nur aufgeräumt, wenn in den Einstellungen
„Daten löschen“ aktiviert ist. Die tägliche Aufgabe `dsgn_scc_prune_stats`
entfernt alte Zeilen.

## Qualitätssicherung

Vor jeder Weitergabe:

```
phpcs --standard=WordPress .
wp plugin check dsgn-sticky-call
```

Bewusste Abweichungen sind im Code mit `phpcs:ignore` samt Begründung
markiert, etwa das `DROP TABLE` in `uninstall.php`.
