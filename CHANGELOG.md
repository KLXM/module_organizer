# Changelog

## [1.0.0] - 2026-10-05

### Fixed
- 🔤 **Modultitel ohne doppeltes Escaping** – Namen mit „&“ erschienen als „Text &amp; Medien“ (der Core liefert die Titel bereits escaped, das JS setzt sie per `textContent`) (#1)
- 🗂️ **Kategorien in Organizer-Reihenfolge** – Popover und Overlay-Seitenleiste sortierten alphabetisch bzw. nach ID statt in der Reihenfolge des Strukturbaums (#1)
- 🔢 **Overlay „Alle“ nach Kategorie sortiert** – Module verschiedener Kategorien wurden durchmischt, weil die Modul-Priorität je Kategorie neu beginnt (#1)

### Changed
- 📖 README erweitert: Installation, Einrichtung (Kategorien, Favoriten, Beschreibungen, Icons, Darstellung), Einrichtung per Skript inkl. Icon-Schlüsseln und SVG-Stil, Hinweise zu Konflikten, Rechten und Technik

## [1.0.0-rc1] - 2026-09-13

- Erste Version: durchsuchbare Blockauswahl als Popover oder Overlay, Kategorien per Drag & Drop, globale und persönliche Favoriten, Beschreibungen, 33 Layout-Icons, Icons aus MediaPlace, Icon-Editor, Dunkelmodus
