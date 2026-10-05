# module_organizer

REDAXO-AddOn, das die Modulauswahl im Content-Editor ("Block hinzufügen") durch eine moderne, durchsuchbare Auswahl ersetzt und eine Verwaltungsoberfläche für Kategorien, Favoriten und Vorschau-Icons bereitstellt.

## Features

- Ersetzt die Bootstrap-Dropdown-Modulauswahl durch ein Overlay, eine geteilte Ansicht (Liste links, große Vorschau rechts) oder ein kompaktes Popover (umschaltbar) – reine Fragment-Überschreibung, kein Eingriff in den Core
- Live-Suche über Titel, Beschreibung und Modul-Key
- Bedienung per Tastatur (Pfeiltasten, Pos1/Ende, Enter fügt den ersten Treffer ein)
- Beschreibungen sichtbar unter dem Modulnamen
- Nummern-Präfixe wie „001 :: “ in der Blockauswahl ausblenden – Regel per regulärem Ausdruck anpassbar
- Kategorien nur in bestimmten Strukturkategorien anbieten (optional inkl. Unterkategorien)
- Nutzung je Modul im Strukturbaum: Anzahl und Seiten mit Link in den Editiermodus
- Kategorisierung der Module per Drag & Drop in einem zweistufigen Strukturbaum (Kategorien → Module)
- Globale (admin-gepflegte) und persönliche (pro Benutzer) Favoriten, die in der Blockauswahl immer zuerst erscheinen
- „Zuletzt verwendet“ je Benutzer – die fünf zuletzt eingefügten Blöcke stehen gleich oben
- 69 neutrale Vorschaubilder (Mockups) je Layout – oder ein eigener Screenshot aus dem Medienpool
- Kurze Beschreibungstexte pro Modul, die als Tooltip in der Blockauswahl erscheinen
- 69 vorgefertigte SVG-Layout-Icons, monochrom oder **Duotone** (zwei Farben, Paletten oder eigene Farben, Akzentfarbe je Kategorie) für gängige Modultypen (Bild, Video, Formular, Cards, Hero, FAQ, Zitat, Kalender, Zeitstrahl, Downloads, WLAN, Katalog u. v. m.)
- Eigene Bilder aus MediaPlace/Medienpool als Icon wählbar
- SVG-Code einfügen: eigene Icons per Copy & Paste – serverseitig bereinigt (keine Skripte, Event-Attribute oder externen Verweise)
- Vektor-Editor für eigene Icons und Vorschaubilder: Vollbild, Bausteine, alle Vorlagen als bearbeitbare Formen, Mehrfachauswahl, Ausrichten, Rückgängig, Live-Vorschau monochrom und Duotone
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

1. **Vorlagen** – 69 Layout-Icons, alphabetisch sortiert und **durchsuchbar** (auch nach Synonymen: „Accordion“, „Reiter“, „Kacheln“, „Fließtext“ …) – Hero, Cards, Raster/Grid, Nur Text, Akkordeon, Bild + Text, FAQ, Formular, Karte, Statistiken, Zitat, Slideshow, Bild-Teaser, Seiten-Navigation, Zeitstrahl, Kalender, Downloads, Standorte, Kontakt, Katalog mit Ort, Info-Karten, Mappe, WLAN, geschützter Bereich Beginn/Ende, Inhalt einbinden, Tabs, Stimmen, Logos, Schritte, Countdown, News, Produkt, Vorher/Nachher, Audio, Diagramm, Öffnungszeiten, Preisliste, Hinweis, Karussell, Masonry, Suche, gestapelte Karten …).
2. **Bild aus dem Medienpool/MediaPlace** – z. B. ein Screenshot des Moduls als echte Vorschau.
3. **SVG-Code einfügen** – eigenes Icon per Copy & Paste. Das SVG wird beim Speichern bereinigt: Skripte, Event-Attribute (`on…`), `foreignObject`, externe Verweise und `javascript:`/`data:`-Werte werden entfernt; ungültiges Markup wird abgelehnt.
4. **Icon-Editor** – eigenes Icon im Backend zeichnen; das SVG wird serverseitig erzeugt (siehe unten).

Alle Icons nutzen `currentColor` und passen sich damit Hell- und Dunkelmodus an.

### Vektor-Editor (Icons und Vorschaubilder)

**Module → Module Organizer** → Modul wählen → *Eigenes Icon zeichnen* bzw. *Vorschaubild zeichnen*. Eigene Werke lassen sich später über den Stift wieder öffnen und bearbeiten (auch *Als Kopie speichern*).

- **Aufbau wie MediaPlace:** links Werkzeuge, Bausteine und Vorlagen, in der Mitte die Zeichenfläche mit dem echten Ergebnis, rechts Eigenschaften und Vorschau (monochrom, Duotone, Originalgröße). **Vollbild** per Knopf in der Kopfleiste – der Zustand wird gemerkt.
- **Formen:** Rechteck, Kreis, Linie, Pfeil, Stern, Haken, Marker, Überschrift, Text, Button, Bild, Video, Dokument, Formular, Browserfenster. Icons bis 16, Vorschaubilder bis 60 Formen.
- **Bausteine:** fertige Gruppen zum Einfügen – Navigation, Bühne, Bild + Text, Karte, 3 Karten, Galerie, Formularzeile, Zitat, Kennzahlen, Liste, Akkordeon, Fußzeile.
- **Icon oder Vorschaubild:** oben in der Kopfleiste umschalten (24×18 bzw. 16:10) – gespeichert wird entsprechend als Icon oder Vorschaubild des gewählten Moduls.
- **Vorlagen:** alle 69 Icon- und Vorschau-Vorlagen lassen sich *als Formen laden* und abwandeln; eigene Werke ebenso. Geschwungene Teile mancher Icons (und eingefügtes SVG) lassen sich *durchpausen* – sie liegen blass unter der Fläche.
- **Bearbeiten:** Auswahl per Klick, Umschalt+Klick oder Aufziehen; acht Anfasser (Umschalt hält das Seitenverhältnis), X/Y/Breite/Höhe als Zahlen, Ausrichten (an der Fläche oder an der Auswahl), Verteilen, Ebenen, Duplizieren, Kopieren/Einfügen, Rückgängig/Wiederholen, Raster und Einrasten.
- **Füllung je Form:** *Kontur*, *Hauch*, *Akzent*, *Voll* und *Grau* – Hauch und Akzent erscheinen bei Duotone in der Akzentfarbe, Grau bleibt neutral.
- **Tastatur:** Pfeile verschieben (Umschalt = größere Schritte, Alt = Größe), 1–5 Füllung, Strg/Cmd+Z/Umschalt+Z, C/X/V, D, A, Entf, Esc.

Der Browser schickt nur Formtyp, Koordinaten und Füllstil – das SVG baut `CustomIconRenderer` auf dem Server; die Formen werden mitgespeichert, damit ein Werk wieder bearbeitbar ist.

### Vorschaubilder

Neben dem Icon kann jedes Modul ein großes Vorschaubild haben: *Automatisch* (passend zum Icon), eine der 69 Vorlagen, ein im Editor gezeichnetes, *Keins* oder ein eigenes Bild aus dem Medienpool/MediaPlace (z. B. ein Screenshot des Moduls). Die Vorlagen sind bewusst neutrale Mockups ohne Inhalte – sie verraten nichts über das Projekt und folgen dem Duotone-Stil.

### Icon-Stil: Monochrom oder Duotone

In den Einstellungen wählbar:

- **Monochrom** – eine Farbe, passt sich dem Backend an (Standard).
- **Duotone** – Konturen und Texte in der ersten Farbe, Flächen in einer Akzentfarbe: zarte Grundflächen als Hauch, kräftigere Flächen (Bildflächen, Buttons) deutlich farbig. Paletten *REDAXO*, *Ozean*, *Warm*, *Natur*, *Beere*, *Violett* oder zwei eigene Farben – mit Live-Vorschau. Im Dunkelmodus werden die Konturen automatisch hell.
- **Akzentfarbe je Kategorie** – über *Einstellungen der Kategorie* (Regler-Symbol am Kategorienkopf). Die Icons einer Kategorie erscheinen dann in ihrer Farbe, ein Farbpunkt markiert die Kategorie im Strukturbaum.

Duotone funktioniert mit allen Vorlagen und mit eigenen Icons im gleichen Aufbau: Flächen mit `fill="currentColor"` und `fill-opacity` (bis 0.15 = Grundfläche, ab 0.18 = Akzent), Konturen mit `stroke="currentColor"`. Bilder aus dem Medienpool bleiben unverändert.

### Darstellung (Einstellungen)

- **Popover** – kompakte Liste direkt am Button „Block hinzufügen“, nach Kategorie gruppiert, Favoriten zuerst.
- **Overlay** – großes Fenster mit Kachel-Raster und Kategorien links (ähnlich MediaPlace). Kacheln wahlweise mit Icons oder mit Vorschaubildern.
- **Geteilt** – Liste links (Favoriten, Zuletzt verwendet, Kategorien), rechts das große Vorschaubild des markierten Blocks mit Beschreibung und Button *Einfügen*. Ideal für Redakteure, die die Blöcke noch nicht kennen.

„Zuletzt verwendet“ merkt sich je Benutzer die fünf zuletzt eingefügten Blöcke (Popover, Overlay und geteilte Ansicht).

**Kategorien auf- und zuklappen:** In der kompakten Liste und der geteilten Ansicht lässt sich jede Kategorie per Klick auf ihren Kopf auf- und zuklappen; die Zahl zeigt, wie viele Blöcke darin liegen. In den Einstellungen wird festgelegt, ob die Kategorien beim Öffnen **aufgeklappt** (Standard) oder **zugeklappt** sind – praktisch bei vielen Modulen. Was ein Benutzer auf- oder zuklappt, merkt sich der Browser. Favoriten und „Zuletzt verwendet“ bleiben immer offen, bei einer Suche sind alle Treffer zu sehen.

**Kategorien nach Benutzerrechten:** Die Blockauswahl zeigt nur Module, für die der Benutzer über seine REDAXO-Rolle Rechte hat. Eine Kategorie, in der er kein einziges Modul nutzen darf, erscheint deshalb gar nicht erst – eine eigene Rechteverwaltung im Organizer ist nicht nötig.

In beiden Modi gibt es die Live-Suche über Titel, Beschreibung und Modul-Key. Mit der Tastatur: **Pfeil runter** springt aus der Suche in die Liste, **Enter** fügt den ersten Treffer ein, die **Pfeiltasten** wandern durch die Kacheln (im Overlay zeilenweise wie sichtbar), **Pos1/Ende** springen an Anfang und Ende, **Esc** schließt.

### Modulnamen ohne Nummern-Präfix

Viele sortieren ihre Module mit Präfixen wie `001 :: Text & Medien`. In den Einstellungen lässt sich das Präfix in der Blockauswahl ausblenden – die Modulnamen selbst bleiben unverändert, die Suche findet Module weiterhin auch über den vollen Namen.

- **Standardregel:** Nummer (optional mit Buchstaben) gefolgt von `::`, `-`, `–`, `|`, `.` oder `:` – z. B. `001 :: Text`, `12 - Teaser`, `003. Hero`, `A01 | Karte`.
- **Eigene Regel:** regulärer Ausdruck ohne Begrenzer, z. B. `^\[\w+\]\s*` für `[hero] Bühne`. Eine Vorschau zeigt, wie die eigenen Module erscheinen; ungültige Ausdrücke werden nicht gespeichert.

### Kategorien nur in bestimmten Bereichen

Über das Regler-Symbol (*Einstellungen der Kategorie*) am Kategorienkopf lässt sich eine Kategorie auf bestimmte Strukturkategorien beschränken, optional inklusive Unterkategorien – z. B. Blöcke für eine Gästemappe nur in der Gästemappe, Landingpage-Blöcke nur unter „Aktionen“. Nichts ausgewählt = überall. Bereits eingesetzte Blöcke bleiben unverändert. Ein Hinweis am Kategorienkopf zeigt die gewählten Bereiche.

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

// Akzentfarbe der Kategorie (Duotone) – leer = Palette
CategoryRepository::saveColor($text['id'], '#4b9ad9');
rex_addon::get('module_organizer')->setConfig('icon_style', 'duotone');   // mono | duotone
rex_addon::get('module_organizer')->setConfig('icon_palette', 'ocean');   // redaxo, ocean, warm, nature, berry, violet, custom
```

**Eigene SVGs im Stil der Vorlagen:** `viewBox="0 0 24 18"`, Flächen `fill="currentColor"` mit `fill-opacity` 0.08–0.35, Konturen `stroke="currentColor"` mit `stroke-width` 1.2–1.5. `CustomIconRepository::save()` bereinigt jedes SVG (`SvgSanitizer`) und wirft bei ungültigem Markup eine `InvalidArgumentException`.

## Hinweise

- **Konflikte:** Andere AddOns, die ebenfalls die Modulauswahl ersetzen (`module_preview`, `nv_modulepreview`), überschreiben die Blockauswahl. Der Organizer warnt davor (abschaltbar in den Einstellungen).
- **Rechte:** Die Verwaltung (Kategorien, globale Favoriten, Icons) ist Admins vorbehalten. Alle Redakteure sehen die geordnete Auswahl und können persönliche Favoriten setzen. Es erscheinen nur Module, die für das Template/den Bereich erlaubt sind.
- **Update:** Neue Spalten (Bereiche und Farbe je Kategorie) legt `update.php` an; vorhandene Kategorien, Zuordnungen und Icons bleiben erhalten.
- **Technik:** Die Blockauswahl wird über eine Fragment-Überschreibung (`fragments/module_select.php`) ersetzt – kein Eingriff in den Core. Daten liegen in `rex_module_organizer_category`, `rex_module_organizer_module`, `rex_module_organizer_user_favorite` und `rex_module_organizer_custom_icon`.

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md).

## Credits

**KLXM Crossmedia GmbH** · [https://klxm.de](https://klxm.de)
Thomas Skerbis

## Lizenz

MIT, siehe [LICENSE](LICENSE).
