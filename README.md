# module_organizer

REDAXO-AddOn, das die Modulauswahl im Content-Editor ("Block hinzufügen") durch eine moderne, durchsuchbare Auswahl ersetzt und eine Verwaltungsoberfläche für Kategorien, Favoriten und Vorschau-Icons bereitstellt.

## Features

- Ersetzt die Bootstrap-Dropdown-Modulauswahl durch ein Overlay oder ein kompaktes Popover (umschaltbar) – reine Fragment-Überschreibung, kein Eingriff in den Core
- Live-Suche über Titel, Beschreibung und Modul-Key
- Kategorisierung der Module per Drag & Drop in einem zweistufigen Strukturbaum (Kategorien → Module)
- Globale (admin-gepflegte) und persönliche (pro Benutzer) Favoriten, die in der Blockauswahl immer zuerst erscheinen
- Kurze Beschreibungstexte pro Modul, die als Tooltip in der Blockauswahl erscheinen
- 33 vorgefertigte, monochrome SVG-Layout-Icons für gängige Modultypen (Bild, Video, Formular, Cards, Hero, FAQ, Zitat u. v. m.)
- Eigene Bilder aus MediaPlace/Medienpool als Icon wählbar
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

1. **Vorlagen** – 33 monochrome Layout-Icons (Hero, Cards, Bild + Text, FAQ, Formular, Karte, Statistiken, Zitat …).
2. **Bild aus dem Medienpool/MediaPlace** – z. B. ein Screenshot des Moduls als echte Vorschau.
3. **Icon-Editor** – eigenes Icon im Backend zeichnen (Text, Bild, Video, Dokument, Formular, Rechteck, Linie); das SVG wird serverseitig erzeugt.

Alle Icons nutzen `currentColor` und passen sich damit Hell- und Dunkelmodus an.

### Darstellung (Einstellungen)

- **Popover** – kompakte Liste direkt am Button „Block hinzufügen“, nach Kategorie gruppiert, Favoriten zuerst.
- **Overlay** – großes Fenster mit Kachel-Raster und Kategorien links (ähnlich MediaPlace); die Icons wirken hier wie kleine Vorschaubilder. Empfehlenswert, sobald Icons gepflegt sind.

In beiden Modi gibt es die Live-Suche über Titel, Beschreibung und Modul-Key.

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

**Icon-Schlüssel:** Vorlagen über ihren Namen (`hero`, `cards`, `faq`, `form`, `map`, `stats`, `divider`, `heading`, `text-media-left` …), Medien mit `media:<dateiname>`, eigene Icons mit `custom:<id>`.

**Eigene SVGs im Stil der Vorlagen:** `viewBox="0 0 24 18"`, Flächen `fill="currentColor"` mit `fill-opacity` 0.08–0.35, Konturen `stroke="currentColor"` mit `stroke-width` 1.2–1.5. Eigene SVGs werden unverändert ausgegeben – nur vertrauenswürdiges Markup speichern (der Icon-Editor erzeugt sicheres SVG serverseitig).

## Hinweise

- **Konflikte:** Andere AddOns, die ebenfalls die Modulauswahl ersetzen (`module_preview`, `nv_modulepreview`), überschreiben die Blockauswahl. Der Organizer warnt davor (abschaltbar in den Einstellungen).
- **Rechte:** Die Verwaltung (Kategorien, globale Favoriten, Icons) ist Admins vorbehalten. Alle Redakteure sehen die geordnete Auswahl und können persönliche Favoriten setzen. Es erscheinen nur Module, die für das Template/den Bereich erlaubt sind.
- **Technik:** Die Blockauswahl wird über eine Fragment-Überschreibung (`fragments/module_select.php`) ersetzt – kein Eingriff in den Core. Daten liegen in `rex_module_organizer_category`, `rex_module_organizer_module`, `rex_module_organizer_user_favorite` und `rex_module_organizer_custom_icon`.

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md).

## Credits

**KLXM Crossmedia GmbH** · [https://klxm.de](https://klxm.de)
Thomas Skerbis

## Lizenz

MIT, siehe [LICENSE](LICENSE).
