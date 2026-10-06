# Changelog

## [1.6.1] - 2026-10-06

### Fixed
- Kategorien in der Blockauswahl ließen sich in manchen Installationen nicht zuklappen: Das `hidden`-Attribut wurde gesetzt, die Einträge blieben aber sichtbar, weil ihre `display`-Regel Vorrang hatte (betrifft kompakte Liste und Liste mit Vorschau; fiel nur auf, wenn das Backend-Theme keine eigene `[hidden]`-Regel mitbringt). Versteckte Einträge sind jetzt immer ausgeblendet.

### Added
- GitHub-Action „Publish release“: veröffentlichte Releases werden automatisch in den REDAXO-Installer übertragen (benötigt die Secrets `MYREDAXO_USERNAME` und `MYREDAXO_API_KEY`)


## [1.6.0] - 2026-10-05

### Added
- 🧩 Neue Vorlagen **Raster / Grid** und **Nur Text** – als Icon und als Vorschaubild (auch als Formen im Editor)
- 🔍 **Suche** über den Icons und den Vorschaubildern: findet Bezeichnung, Schlüssel und Synonyme (z. B. „Accordion“, „Aufklappen“, „Reiter“, „Registerkarten“, „Kacheln“, „Fließtext“, „Bühne“); ein einziger Treffer bei den Vorschaubildern wird direkt gewählt

### Changed
- Icons und Vorschaubilder alphabetisch sortiert (deutsche Sortierung)


## [1.5.0] - 2026-10-05

### Added
- 📂 **Kategorien auf- und zuklappen** in der kompakten Liste und der geteilten Ansicht – Kopf als Knopf mit Pfeil und Anzahl der Blöcke, `aria-expanded`, per Tastatur bedienbar; der Zustand wird je Browser gemerkt
- ⚙️ **Einstellung „Kategorien in der Blockauswahl“:** aufgeklappt (Standard) oder zugeklappt – hilfreich bei vielen Modulen; Favoriten und „Zuletzt verwendet“ bleiben offen, bei der Suche ist immer alles sichtbar

### Changed
- Pfeiltasten springen nur über sichtbare Einträge
- README: Kategorien ohne erlaubte Module (REDAXO-Rollenrechte) erscheinen nicht in der Blockauswahl


## [1.4.2] - 2026-10-05

### Fixed
- ⭐ **Persönliche Favoriten** gelten sofort für alle „Block hinzufügen“-Knöpfe der Seite – bisher hatte jeder Knopf eine eigene Datenkopie, ein gesetzter Favorit erschien an anderer Stelle erst nach dem Neuladen

### Changed
- Stern zum Merken größer und deutlicher (runde Fläche), mit Beschriftung „Zu meinen Favoriten“ / „Aus meinen Favoriten entfernen“ und `aria-pressed`
- Stern jetzt auch in der geteilten Ansicht (Liste mit Vorschau); globale Favoriten dort mit eigenem Symbol


## [1.4.1] - 2026-10-05

### Added
- 🧩 **Icon-Vorlagen als Formen** – alle 67 Icon-Vorlagen lassen sich im Editor laden (`assets/icons/layout-<key>.json`); geschwungene Teile, die es nicht als Form gibt, werden beim Laden automatisch zum Durchpausen eingeblendet
- 🔀 **Umschalter Icon | Vorschaubild** in der Kopfleiste des Editors (bei neuen Werken); vorhandene Formen werden auf die neue Fläche umgerechnet, gespeichert wird je nach Wahl als Icon oder als Vorschaubild des Moduls

### Fixed
- Nach dem Speichern eines Icons wird erst die Zuweisung gespeichert, dann neu geladen


## [1.4.0] - 2026-10-05

### Added
- ✏️ **Vektor-Editor** statt Mini-Editor – Aufbau wie MediaPlace (Werkzeuge, Zeichenfläche mit echtem Ergebnis, Eigenschaften, Vorschau), **Vollbild** (gemerkt), Mehrfachauswahl per Aufziehen/Umschalt, acht Anfasser, Zahlenfelder, Ausrichten/Verteilen, Ebenen, Kopieren/Einfügen, Rückgängig/Wiederholen, Raster und Einrasten
- 🖼️ **Vorschaubilder zeichnen** (16:10) – automatisch dem Modul zugewiesen, in der Auswahl unter „Eigene Vorschaubilder“
- 🧱 **Bausteine** – Navigation, Bühne, Bild + Text, Karte, 3 Karten, Galerie, Formularzeile, Zitat, Kennzahlen, Liste, Akkordeon, Fußzeile
- 📐 **Vorlagen als Formen** – alle 67 Vorschau-Vorlagen (`assets/previews/<key>.json`) und eigene Werke laden und abwandeln; Icon-Vorlagen durchpausen
- 🔁 **Wieder bearbeiten** – eigene Icons und Vorschaubilder speichern ihre Formen (Spalten `kind`, `shapes`), Bearbeiten-Knopf, „Als Kopie speichern“
- 🎨 Neue Formen *Browserfenster* und Füllstil *Grau* (neutral, ohne Akzentfarbe)

### Changed
- Gelöschte eigene Vorschaubilder setzen betroffene Module auf „automatisch“ zurück


## [1.3.0] - 2026-10-05

### Added
- 🖼️ **Vorschaubilder** je Modul: 67 neutrale Mockups (Duotone-fähig), automatisch passend zum Icon, oder eigenes Bild aus Medienpool/MediaPlace (`PreviewRegistry`, Spalte `preview_key`)
- 🪟 **Geteilte Ansicht** – Liste links, großes Vorschaubild mit Beschreibung und *Einfügen* rechts; Overlay-Kacheln wahlweise mit Icons oder Vorschaubildern
- 🕘 **Zuletzt verwendet** je Benutzer (letzte fünf eingefügte Blöcke) in Popover, Overlay und geteilter Ansicht (`UserRecentRepository`, Tabelle `module_organizer_user_recent`)
- 🧩 **17 neue Layout-Icons** (Tabs, Stimmen, Logos, Schritte, Countdown, News, Produkt, Vorher/Nachher, Audio, Diagramm, Öffnungszeiten, Preisliste, Hinweis, Karussell, Masonry, Suche, gestapelte Karten) – jetzt 67 Vorlagen
- ✏️ **Icon-Editor für Duotone** – neue Formen (Überschrift, Kreis, Button, Stern, Marker, Haken, Pfeil), Füllstil je Form (Kontur, Hauch, Akzent, Voll), Duplizieren, Ebenen nach vorn/hinten, Tastatur (Pfeile, Alt+Pfeile, 1–4, Tab, Strg/Cmd+D, Entf), Vorschau monochrom/Duotone/Originalgröße, bis zu 16 Formen


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
