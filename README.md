# module_organizer

REDAXO-AddOn, das die Modulauswahl im Content-Editor ("Block hinzufügen") durch eine moderne, durchsuchbare Auswahl ersetzt und eine Verwaltungsoberfläche für Kategorien, Favoriten und Vorschau-Icons bereitstellt.

## Features

- Ersetzt die Bootstrap-Dropdown-Modulauswahl durch ein Overlay oder ein kompaktes Popover (umschaltbar) – reine Fragment-Überschreibung, kein Eingriff in den Core
- Live-Suche über Titel, Beschreibung und Modul-Key
- Bedienung per Tastatur (Pfeiltasten, Pos1/Ende, Enter fügt den ersten Treffer ein)
- Beschreibungen sichtbar unter dem Modulnamen
- Nummern-Präfixe wie „001 :: “ in der Blockauswahl ausblenden – Regel per regulärem Ausdruck anpassbar
- Kategorien nur in bestimmten Strukturkategorien anbieten (optional inkl. Unterkategorien)
- Nutzung je Modul im Strukturbaum: Anzahl und Seiten mit Link in den Editiermodus
- Kategorisierung der Module per Drag & Drop in einem zweistufigen Strukturbaum (Kategorien → Module)
- Globale (admin-gepflegte) und persönliche (pro Benutzer) Favoriten, die in der Blockauswahl immer zuerst erscheinen
- Kurze Beschreibungstexte pro Modul, die als Tooltip in der Blockauswahl erscheinen
- 50 vorgefertigte, monochrome SVG-Layout-Icons für gängige Modultypen (Bild, Video, Formular, Cards, Hero, FAQ, Zitat, Kalender, Zeitstrahl, Downloads, WLAN, Katalog u. v. m.)
- Eigene Bilder aus MediaPlace/Medienpool als Icon wählbar
- SVG-Code einfügen: eigene Icons per Copy & Paste – serverseitig bereinigt (keine Skripte, Event-Attribute oder externen Verweise)
- Mini-Icon-Editor: eigene Icons direkt im Backend zeichnen (Rechtecke, freie Linien, Platzhalter-Typen), inklusive Live-Vorschau des tatsächlich gespeicherten SVGs
- Vollständige Unterstützung des REDAXO-Dunkelmodus (manuell gewählt und systemabhängig)
- Mehrsprachig (Deutsch/Englisch)

## Installation

1. AddOn über den Installer oder als Ordner `module_organizer` installieren (REDAXO ≥ 5.20, PHP ≥ 8.1).
2. Bei der Installation werden alle vorhandenen Module in ihrer bisherigen Reihenfolge übernommen – die Blockauswahl funktioniert sofort.
3. Unter **Module → Module Organizer** Kategorien, Favoriten, Beschreibungen und Icons pflegen (nur Admins).

## Einrichtung

### Kategorien und Reihenfolge

Im Strukturbaum (**Module → Module Organizer**) Kategorien anlegen, umbenennen und löschen. Module und Kategorien lassen sich per Drag & Drop sortieren und zwischen Kategorien verschieben. Die Reihenfolge im Baum gilt auch in der Blockauswahl – Kategorien, dann Module innerhalb der Kategorie. Module ohne Kategorie erscheinen zuletzt.

Bewährt hat sich eine Handvoll Kategorien nach Aufgabe statt nach Technik, z. B.:

| Kategorie | Module |
|-----------|--------|
| Text & Bild | Text mit Bild, Überschrift, Kacheln, Downloads, Abstand |
| Bühne & Bilder | Hero, Slideshow, Bild-Teaser |
| Landingpage | Seitennavigation, Kennzahlen, Zeitstrahl, FAQ, Call-to-Action |
| Kontakt | Kontakt & Anfahrt, Karte, Formular, Kalender |
| Spezial | Inhalte einer anderen Seite einbinden |

### Favoriten

- **Globale Favoriten** (Stern im Strukturbaum, nur Admins) stehen für alle Benutzer ganz oben in der Blockauswahl – ideal für die zwei, drei meistgenutzten Module.
- **Persönliche Favoriten** setzt jeder Benutzer selbst mit dem Stern direkt in der Blockauswahl.

### Beschreibungen

Ein kurzer Satz je Modul („Großes Bild über die ganze Breite – für den Seitenanfang“) erscheint in der Blockauswahl als Hinweis und wird von der Suche erfasst. Gerade für Redakteure, die die Module nicht kennen, ist das die größte Hilfe.

### Icons

Jedes Modul kann ein Vorschau-Icon bekommen:

1. **Vorlagen** – 50 monochrome Layout-Icons (Hero, Cards, Bild + Text, FAQ, Formular, Karte, Statistiken, Zitat, Slideshow, Bild-Teaser, Seiten-Navigation, Zeitstrahl, Kalender, Downloads, Standorte, Kontakt, Katalog mit Ort, Info-Karten, Mappe, WLAN, geschützter Bereich Beginn/Ende, Inhalt einbinden …).
2. **Bild aus dem Medienpool/MediaPlace** – z. B. ein Screenshot des Moduls als echte Vorschau.
3. **SVG-Code einfügen** – eigenes Icon per Copy & Paste. Das SVG wird beim Speichern bereinigt: Skripte, Event-Attribute (`on…`), `foreignObject`, externe Verweise und `javascript:`/`data:`-Werte werden entfernt; ungültiges Markup wird abgelehnt.
4. **Icon-Editor** – eigenes Icon im Backend zeichnen (Text, Bild, Video, Dokument, Formular, Rechteck, Linie); das SVG wird serverseitig erzeugt.

Alle Icons nutzen `currentColor` und passen sich damit Hell- und Dunkelmodus an.

### Darstellung (Einstellungen)

- **Popover** – kompakte Liste direkt am Button „Block hinzufügen“, nach Kategorie gruppiert, Favoriten zuerst.
- **Overlay** – großes Fenster mit Kachel-Raster und Kategorien links (ähnlich MediaPlace); die Icons wirken hier wie kleine Vorschaubilder. Empfehlenswert, sobald Icons gepflegt sind.

In beiden Modi gibt es die Live-Suche über Titel, Beschreibung und Modul-Key. Mit der Tastatur: **Pfeil runter** springt aus der Suche in die Liste, **Enter** fügt den ersten Treffer ein, die **Pfeiltasten** wandern durch die Kacheln (im Overlay zeilenweise wie sichtbar), **Pos1/Ende** springen an Anfang und Ende, **Esc** schließt.

### Modulnamen ohne Nummern-Präfix

Viele sortieren ihre Module mit Präfixen wie `001 :: Text & Medien`. In den Einstellungen lässt sich das Präfix in der Blockauswahl ausblenden – die Modulnamen selbst bleiben unverändert, die Suche findet Module weiterhin auch über den vollen Namen.

- **Standardregel:** Nummer (optional mit Buchstaben) gefolgt von `::`, `-`, `–`, `|`, `.` oder `:` – z. B. `001 :: Text`, `12 - Teaser`, `003. Hero`, `A01 | Karte`.
- **Eigene Regel:** regulärer Ausdruck ohne Begrenzer, z. B. `^\[\w+\]\s*` für `[hero] Bühne`. Eine Vorschau zeigt, wie die eigenen Module erscheinen; ungültige Ausdrücke werden nicht gespeichert.

### Kategorien nur in bestimmten Bereichen

Über das Symbol <i>Bereiche</i> am Kategorienkopf lässt sich eine Kategorie auf bestimmte Strukturkategorien beschränken, optional inklusive Unterkategorien – z. B. Blöcke für eine Gästemappe nur in der Gästemappe, Landingpage-Blöcke nur unter „Aktionen“. Nichts ausgewählt = überall. Bereits eingesetzte Blöcke bleiben unverändert. Ein Hinweis am Kategorienkopf zeigt die gewählten Bereiche.

Welche Module ein Benutzer überhaupt nutzen darf, regeln weiterhin die REDAXO-Rollen; welche Module in einem Template erlaubt sind, die Template-Einstellungen.

### Nutzung der Module

Im Strukturbaum steht hinter jedem Modul, wie oft es eingesetzt ist (ungenutzte Module rot). Ein Klick auf das Modul zeigt in der Seitenleiste die Seiten, auf denen es vorkommt, mit Link in den Editiermodus – praktisch zum Aufräumen und bevor ein Modul geändert wird. Die Organizer-Seite ist Admins vorbehalten.

## Einrichtung per Skript

Für mehrere Installationen oder Deployments lässt sich die Ordnung auch per PHP anlegen (z. B. in einem Setup-Skript):

```php
use KLXM\ModuleOrganizer\Repository\CategoryRepository;
use KLXM\ModuleOrganizer\Repository\CustomIconRepository;
use KLXM\ModuleOrganizer\Repository\ModuleMetaRepository;

$text = CategoryRepository::create('Text & Bild');            // ['id' => …, 'name' => …]
$stage = CategoryRepository::create('Bühne & Bilder');

// Modul-ID, Kategorie, globaler Favorit, Beschreibung, Icon
ModuleMetaRepository::save(8, $text['id'], true, 'Text mit Bild, Galerie oder Slider.', 'text-media-left');
ModuleMetaRepository::save(46, $stage['id'], true, 'Großes Bild über die ganze Breite.', 'hero');

// Reihenfolge innerhalb einer Kategorie
ModuleMetaRepository::saveGroupOrder($text['id'], [8, 7, 1]);

// Eigenes SVG-Icon (viewBox 0 0 24 18, currentColor) und zuweisen
$iconId = CustomIconRepository::save(null, 'Kalender', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 18">…</svg>');
ModuleMetaRepository::save(14, $text['id'], false, 'Veranstaltungen aus dem Kalender.', 'custom:' . $iconId);
```

**Icon-Schlüssel:** Vorlagen über ihren Namen (`hero`, `cards`, `faq`, `form`, `map`, `stats`, `divider`, `heading`, `text-media-left`, `slideshow`, `timeline`, `calendar`, `downloads`, `wifi`, `folder` …), Medien mit `media:<dateiname>`, eigene Icons mit `custom:<id>`.

```php
// Kategorie nur in Strukturkategorie 12 und darunter anbieten
CategoryRepository::saveAreas($text['id'], [12], true);
```

**Eigene SVGs im Stil der Vorlagen:** `viewBox="0 0 24 18"`, Flächen `fill="currentColor"` mit `fill-opacity` 0.08–0.35, Konturen `stroke="currentColor"` mit `stroke-width` 1.2–1.5. `CustomIconRepository::save()` bereinigt jedes SVG (`SvgSanitizer`) und wirft bei ungültigem Markup eine `InvalidArgumentException`.

## Hinweise

- **Konflikte:** Andere AddOns, die ebenfalls die Modulauswahl ersetzen (`module_preview`, `nv_modulepreview`), überschreiben die Blockauswahl. Der Organizer warnt davor (abschaltbar in den Einstellungen).
- **Rechte:** Die Verwaltung (Kategorien, globale Favoriten, Icons) ist Admins vorbehalten. Alle Redakteure sehen die geordnete Auswahl und können persönliche Favoriten setzen. Es erscheinen nur Module, die für das Template/den Bereich erlaubt sind.
- **Update:** Neue Spalten (Bereiche je Kategorie) legt `update.php` an; vorhandene Kategorien, Zuordnungen und Icons bleiben erhalten.
- **Technik:** Die Blockauswahl wird über eine Fragment-Überschreibung (`fragments/module_select.php`) ersetzt – kein Eingriff in den Core. Daten liegen in `rex_module_organizer_category`, `rex_module_organizer_module`, `rex_module_organizer_user_favorite` und `rex_module_organizer_custom_icon`.

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md).

## Credits

**KLXM Crossmedia GmbH** · [https://klxm.de](https://klxm.de)
Thomas Skerbis

## Lizenz

MIT, siehe [LICENSE](LICENSE).
