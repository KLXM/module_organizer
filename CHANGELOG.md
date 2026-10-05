# Changelog

## [1.2.0] - 2026-10-05

### Added
- 🎨 **Icon-Stil Duotone** – Konturen in der ersten, Flächen in einer Akzentfarbe; Paletten REDAXO, Ozean, Warm, Natur, Beere, Violett oder eigene Farben, Live-Vorschau in den Einstellungen; Dunkelmodus mit hellen Konturen; gilt für alle 50 Vorlagen und eigene Icons im gleichen Aufbau
- 🏷️ **Akzentfarbe je Kategorie** (`CategoryRepository::saveColor()`), Farbpunkt im Strukturbaum; die Bereichs-Einstellungen heißen jetzt „Einstellungen der Kategorie“


## [1.1.0] - 2026-10-05

### Added
- ⌨️ **Tastatur-Bedienung** in Popover und Overlay: Pfeil runter aus der Suche in die Liste, Enter fügt den ersten Treffer ein, Pfeiltasten (im Overlay zeilenweise), Pos1/Ende, Pfeil hoch zurück zur Suche
- 💬 **Beschreibungen sichtbar** unter dem Modulnamen (statt nur als Tooltip – auch für Touch- und Tastaturnutzer)
- ✂️ **Nummern-Präfix ausblenden** (z. B. „001 :: Text“ → „Text“) mit Standardregel und eigener Regel als regulärem Ausdruck, Live-Vorschau in den Einstellungen; die Suche findet weiterhin den vollen Namen
- 🗺️ **Kategorien nur in bestimmten Strukturkategorien** anbieten, optional inklusive Unterkategorien (`CategoryRepository::saveAreas()`)
- 📊 **Nutzung je Modul** im Strukturbaum (Anzahl, ungenutzte rot) und in der Seitenleiste (Seiten mit Link in den Editiermodus)
- 🧩 **17 neue Layout-Icons** (Downloads, Slideshow, Bild-Teaser, Seiten-Navigation, Zeitstrahl, Deko am Seitenrand, Begrüßung, Standorte, Kontakt, Kalender, Katalog mit Ort, Info-Karten, Mappe, WLAN, geschützter Bereich Beginn/Ende, Inhalt einbinden) – jetzt 50 Vorlagen
- 📋 **SVG-Code einfügen** als eigenes Icon

### Security
- 🛡️ **SVG-Bereinigung** (`SvgSanitizer`) für alle eigenen Icons – Icon-Editor, eingefügter Code und `CustomIconRepository::save()`: Allowlist für Elemente/Attribute, entfernt Skripte, Event-Attribute, `foreignObject`, externe Verweise, `javascript:`/`data:`; DOCTYPE/Entities werden abgelehnt


## [1.0.0] - 2026-10-05

### Fixed
- 🔤 **Modultitel ohne doppeltes Escaping** – Namen mit „&“ erschienen als „Text &amp; Medien“ (der Core liefert die Titel bereits escaped, das JS setzt sie per `textContent`) (#1)
- 🗂️ **Kategorien in Organizer-Reihenfolge** – Popover und Overlay-Seitenleiste sortierten alphabetisch bzw. nach ID statt in der Reihenfolge des Strukturbaums (#1)
- 🔢 **Overlay „Alle“ nach Kategorie sortiert** – Module verschiedener Kategorien wurden durchmischt, weil die Modul-Priorität je Kategorie neu beginnt (#1)

### Changed
- 📖 README erweitert: Installation, Einrichtung (Kategorien, Favoriten, Beschreibungen, Icons, Darstellung), Einrichtung per Skript inkl. Icon-Schlüsseln und SVG-Stil, Hinweise zu Konflikten, Rechten und Technik

## [1.0.0-rc1] - 2026-09-13

- Erste Version: durchsuchbare Blockauswahl als Popover oder Overlay, Kategorien per Drag & Drop, globale und persönliche Favoriten, Beschreibungen, 33 Layout-Icons, Icons aus MediaPlace, Icon-Editor, Dunkelmodus
