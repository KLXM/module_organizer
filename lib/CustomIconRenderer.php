<?php

namespace KLXM\ModuleOrganizer;

/**
 * Baut aus einer vom Icon-Editor (assets/module_organizer_icon_editor.js)
 * gelieferten Formen-Liste (Typ, Koordinaten, Füllstil) serverseitig valides SVG-Markup - der Client
 * liefert NIE rohes SVG, nur Typ+Koordinaten (Sicherheit: verhindert
 * SVG-basiertes XSS ueber die innerHTML-Rendering-Stellen in
 * assets/module_organizer.js und assets/module_organizer_popover.js).
 * Haelt den Stil konsistent mit den statischen Presets unter assets/icons/
 * (viewBox 0..24 x 0..18, currentColor mit reduzierter fill-opacity fuer
 * Flaechen, volle Deckkraft fuer Konturen/Akzente).
 */
class CustomIconRenderer
{
    public const CANVAS_WIDTH = 24;
    public const CANVAS_HEIGHT = 18;
    public const MAX_SHAPES = 16;

    /**
     * Zeichenflächen: Icon (24×18) und Vorschaubild (32×20 = 16:10, wie die Vorschau-Vorlagen).
     * [Breite, Höhe, max. Formen, Strichstärken-Faktor]
     *
     * @var array<string, array{0: int, 1: int, 2: int, 3: float}>
     */
    public const KINDS = [
        'icon' => [24, 18, 16, 1.0],
        'preview' => [32, 20, 60, 0.4],
    ];

    /** Strichstärken-Faktor der aktuellen Zeichenfläche (Vorschaubilder zeichnen feiner) */
    private static float $strokeScale = 1.0;
    private static string $kind = 'icon';

    /**
     * Füllstile je Form – passend zur Duotone-Konvention der Vorlagen-Icons:
     * outline = nur Kontur, soft = zarte Fläche (fill-opacity < .18 → Hauch der Akzentfarbe),
     * accent = kräftige Fläche (fill-opacity ≥ .18 → Akzentfarbe), solid = volle Konturfarbe,
     * muted = graue Fläche ohne Akzent (opacity statt fill-opacity – Duotone färbt sie nicht).
     *
     * @var list<string>
     */
    public const STYLES = ['outline', 'soft', 'accent', 'solid', 'muted'];

    /** @var list<string> Formen aus zwei Punkten statt Box */
    public const LINE_TYPES = ['line', 'arrow'];

    /** @return list<string> */
    public static function getValidTypes(): array
    {
        return ['text', 'heading', 'image', 'video', 'media', 'form', 'rect', 'circle', 'button', 'star', 'pin', 'check', 'browser', 'line', 'arrow'];
    }

    /** Vorgabe-Stil, wenn der Client keinen (gültigen) liefert */
    public static function defaultStyle(string $type): string
    {
        return match ($type) {
            'heading' => 'solid',
            'browser' => 'muted',
            'button', 'star', 'pin', 'check' => 'accent',
            'line', 'arrow', 'text' => 'solid',
            default => 'soft',
        };
    }

    /**
     * @param mixed $shapes rohe, noch ungeprüfte Client-Eingabe (json_decode-Ergebnis)
     * @return list<array{x: float, y: float, w: float, h: float, type: string, style: string, x2?: float, y2?: float}>
     */
    public static function sanitizeShapes($shapes, string $kind = 'icon'): array
    {
        if (!is_array($shapes)) {
            return [];
        }
        [$canvasW, $canvasH, $maxShapes] = self::KINDS[$kind] ?? self::KINDS['icon'];

        $validTypes = self::getValidTypes();
        $result = [];

        foreach ($shapes as $shape) {
            if (count($result) >= $maxShapes) {
                break;
            }
            if (!is_array($shape)) {
                continue;
            }

            $type = is_string($shape['type'] ?? null) ? $shape['type'] : '';
            if (!in_array($type, $validTypes, true)) {
                continue;
            }

            $style = is_string($shape['style'] ?? null) && in_array($shape['style'], self::STYLES, true) ? $shape['style'] : self::defaultStyle($type);

            // "line"/"arrow" ist eine freie Strecke zwischen zwei Punkten statt einer
            // Box - eigene, einfachere Validierung (nur Punkte in Canvas-
            // Grenzen klemmen, kein Mindest-Rechteck noetig).
            if (in_array($type, self::LINE_TYPES, true)) {
                $x = self::clampFloat($shape['x'] ?? 0, 0, $canvasW);
                $y = self::clampFloat($shape['y'] ?? 0, 0, $canvasH);
                $x2 = self::clampFloat($shape['x2'] ?? 0, 0, $canvasW);
                $y2 = self::clampFloat($shape['y2'] ?? 0, 0, $canvasH);
                if (abs($x2 - $x) < 0.2 && abs($y2 - $y) < 0.2) {
                    continue;
                }
                $result[] = ['x' => $x, 'y' => $y, 'x2' => $x2, 'y2' => $y2, 'w' => 0.0, 'h' => 0.0, 'type' => $type, 'style' => $style];
                continue;
            }

            $x = self::clampFloat($shape['x'] ?? 0, 0, $canvasW);
            $y = self::clampFloat($shape['y'] ?? 0, 0, $canvasH);
            $w = self::clampFloat($shape['w'] ?? 0, 0.5, $canvasW);
            $h = self::clampFloat($shape['h'] ?? 0, 0.5, $canvasH);

            // Rechteck darf nicht ueber den Canvas-Rand hinausragen.
            $w = min($w, $canvasW - $x);
            $h = min($h, $canvasH - $y);
            if ($w < 0.5 || $h < 0.5) {
                continue;
            }

            $result[] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'type' => $type, 'style' => $style];
        }

        return $result;
    }

    private static function clampFloat(mixed $value, float $min, float $max): float
    {
        $float = is_numeric($value) ? (float) $value : 0.0;

        return max($min, min($max, $float));
    }

    /**
     * @param list<array{x: float, y: float, w: float, h: float, type: string, style: string, x2?: float, y2?: float}> $shapes
     */
    public static function render(array $shapes, string $kind = 'icon'): string
    {
        $kind = isset(self::KINDS[$kind]) ? $kind : 'icon';
        [$canvasW, $canvasH, , $scale] = self::KINDS[$kind];
        self::$strokeScale = $scale;
        self::$kind = $kind;
        $body = '';
        foreach ($shapes as $shape) {
            $body .= self::renderShape($shape);
        }
        self::$strokeScale = 1.0;
        self::$kind = 'icon';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $canvasW . ' ' . $canvasH
            . '" role="img" aria-hidden="true">' . $body . '</svg>';
    }

    private static function sw(float $width): string
    {
        return self::num($width * self::$strokeScale);
    }

    /**
     * @param array{x: float, y: float, w: float, h: float, type: string, style: string, x2?: float, y2?: float} $shape
     */
    private static function renderShape(array $shape): string
    {
        $style = $shape['style'];
        if (in_array($shape['type'], self::LINE_TYPES, true)) {
            return self::renderLine($shape['x'], $shape['y'], $shape['x2'] ?? $shape['x'], $shape['y2'] ?? $shape['y'], 'arrow' === $shape['type'], $style);
        }

        $x = $shape['x'];
        $y = $shape['y'];
        $w = $shape['w'];
        $h = $shape['h'];

        return match ($shape['type']) {
            'image' => self::renderImage($x, $y, $w, $h, $style),
            'video' => self::renderVideo($x, $y, $w, $h, $style),
            'media' => self::renderMedia($x, $y, $w, $h, $style),
            'form' => self::renderForm($x, $y, $w, $h, $style),
            'rect' => self::renderRect($x, $y, $w, $h, $style),
            'circle' => self::renderCircle($x, $y, $w, $h, $style),
            'button' => self::renderButton($x, $y, $w, $h, $style),
            'heading' => self::renderHeading($x, $y, $w, $h, $style),
            'star' => self::renderStar($x, $y, $w, $h, $style),
            'pin' => self::renderPin($x, $y, $w, $h, $style),
            'check' => self::renderCheck($x, $y, $w, $h, $style),
            'browser' => self::renderBrowser($x, $y, $w, $h, $style),
            default => self::renderText($x, $y, $w, $h, $style),
        };
    }

    /**
     * Füll-/Kontur-Attribute für Flächen nach Stil.
     * Duotone färbt alles mit fill-opacity in der Akzentfarbe (zart < .18, kräftig ab .18),
     * Konturen und volle Flächen bleiben in der ersten Farbe.
     */
    private static function paint(string $style): string
    {
        // Vorschaubilder: Konturen zarter (wie die Vorlagen)
        $soft = 'preview' === self::$kind ? ' stroke-opacity="0.4"' : '';
        $width = 'preview' === self::$kind ? '0.1' : self::sw(0.6);

        return match ($style) {
            'outline' => 'fill="none" stroke="currentColor" stroke-width="' . $width . '"' . $soft,
            'accent' => 'fill="currentColor" fill-opacity="0.35"',
            'solid' => 'fill="currentColor"',
            'muted' => 'fill="currentColor" opacity="0.08"',
            default => 'fill="currentColor" fill-opacity="0.12" stroke="currentColor" stroke-width="' . $width . '"' . $soft,
        };
    }

    /** Linien/Texte haben keine Fläche: zarte Stile werden über die Deckkraft der Kontur abgebildet */
    /** Farbe für Details auf der Fläche – auf voller Fläche ausgespart (weiß) */
    private static function ink(string $style): string
    {
        return 'solid' === $style ? '#fff' : 'currentColor';
    }

    private static function strokeOpacity(string $style): string
    {
        return match ($style) {
            'soft', 'outline' => ' stroke-opacity="0.55"',
            'muted' => ' stroke-opacity="0.3"',
            default => '',
        };
    }

    private static function rect(float $x, float $y, float $w, float $h, string $extra = ''): string
    {
        return sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" rx="0.6" %s/>',
            self::num($x),
            self::num($y),
            self::num($w),
            self::num($h),
            $extra,
        );
    }

    private static function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    private static function renderImage(float $x, float $y, float $w, float $h, string $style): string
    {
        if ('preview' === self::$kind) {
            // Fotoplatzhalter wie die Vorschau-Vorlagen: Fläche, gefüllte Berge, Sonne rechts
            $b = $y + $h;
            $fill = 'solid' === $style ? 'fill="#fff" fill-opacity="0.35"' : 'fill="currentColor" fill-opacity="0.5"';

            return self::rect($x, $y, $w, $h, self::paint($style))
                . sprintf(
                    '<path d="M%s %sL%s %sL%s %sL%s %sL%s %sZ" %s/>',
                    self::num($x), self::num($b), self::num($x + $w * 0.3), self::num($y + $h * 0.45), self::num($x + $w * 0.5), self::num($y + $h * 0.7),
                    self::num($x + $w * 0.68), self::num($y + $h * 0.5), self::num($x + $w), self::num($b), $fill,
                )
                . sprintf('<circle cx="%s" cy="%s" r="%s" %s/>', self::num($x + $w * 0.8), self::num($y + $h * 0.28), self::num(max(0.3, min($w, $h) * 0.09)), str_replace('0.5', '0.45', $fill));
        }
        $svg = self::rect($x, $y, $w, $h, self::paint($style));
        $cx = $x + $w * 0.28;
        $cy = $y + $h * 0.3;
        $r = min($w, $h) * 0.12;
        $svg .= sprintf('<circle cx="%s" cy="%s" r="%s" fill="' . self::ink($style) . '"/>', self::num($cx), self::num($cy), self::num(max(0.3, $r)));
        $svg .= sprintf(
            '<path d="M%s %sl%s -%sa1 1 0 0 1 1.4 0l%s %s" fill="none" stroke="' . self::ink($style) . '" stroke-width="' . self::sw(0.6) . '"/>',
            self::num($x + $w * 0.08),
            self::num($y + $h * 0.85),
            self::num($w * 0.3),
            self::num($h * 0.3),
            self::num($w * 0.32),
            self::num($h * 0.32),
        );

        return $svg;
    }

    private static function renderVideo(float $x, float $y, float $w, float $h, string $style): string
    {
        $svg = self::rect($x, $y, $w, $h, self::paint($style));
        $cx = $x + $w / 2;
        $cy = $y + $h / 2;
        $r = min($w, $h) * 0.32;
        $svg .= sprintf('<circle cx="%s" cy="%s" r="%s" fill="' . self::ink($style) . '" fill-opacity="0.18"/>', self::num($cx), self::num($cy), self::num($r));
        // Gleichseitiges Play-Dreieck, mittig im Kreis: die Spitze zeigt nach
        // rechts, der optische Schwerpunkt (nicht die Bounding-Box-Mitte)
        // liegt auf cx/cy - dafuer die linke Kante leicht nach links
        // versetzt (klassischer Trick, sonst wirkt das Dreieck nach links
        // verschoben).
        $triHeight = $r * 1.1;
        $triHalfBase = $triHeight * 0.577; // gleichseitiges Dreieck
        $tipX = $cx + $triHeight * 0.42;
        $baseX = $cx - $triHeight * 0.28;
        $svg .= sprintf(
            '<path d="M%s %sL%s %sL%s %sZ" fill="' . self::ink($style) . '"/>',
            self::num($baseX),
            self::num($cy - $triHalfBase),
            self::num($baseX),
            self::num($cy + $triHalfBase),
            self::num($tipX),
            self::num($cy),
        );

        return $svg;
    }

    private static function renderMedia(float $x, float $y, float $w, float $h, string $style): string
    {
        // Dokument mit umgeknickter Ecke oben rechts.
        $fold = min($w, $h) * 0.28;
        $svg = sprintf(
            '<path d="M%s %sh%sl%s %sv%sh-%sz" ' . self::paint($style) . ' stroke-linejoin="round"/>',
            self::num($x),
            self::num($y),
            self::num($w - $fold),
            self::num($fold),
            self::num($fold),
            self::num($h - $fold),
            self::num($w),
        );
        $svg .= sprintf(
            '<path d="M%s %sv%sh%s" fill="none" stroke="' . self::ink($style) . '" stroke-width="' . self::sw(0.5) . '"/>',
            self::num($x + $w - $fold),
            self::num($y),
            self::num($fold),
            self::num($fold),
        );
        $lineY = $y + $h * 0.55;
        $svg .= sprintf(
            '<path d="M%s %sh%sM%s %sh%s" stroke="' . self::ink($style) . '" stroke-width="' . self::sw(0.6) . '" stroke-linecap="round"/>',
            self::num($x + $w * 0.15),
            self::num($lineY),
            self::num($w * 0.7),
            self::num($x + $w * 0.15),
            self::num($lineY + $h * 0.18),
            self::num($w * 0.5),
        );

        return $svg;
    }

    private static function renderForm(float $x, float $y, float $w, float $h, string $style): string
    {
        $lineH = $h * 0.3;
        $svg = self::rect($x, $y, $w, $lineH, self::paint($style));
        $svg .= self::rect($x, $y + $h * 0.55, $w * 0.6, $lineH, self::paint($style));
        $svg .= sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="currentColor"/>',
            self::num($x + $w * 0.68),
            self::num($y + $h * 0.55),
            self::num($w * 0.32),
            self::num($lineH),
            self::num($lineH / 2),
        );

        return $svg;
    }

    private static function renderRect(float $x, float $y, float $w, float $h, string $style): string
    {
        return self::rect($x, $y, $w, $h, self::paint($style));
    }

    private static function renderLine(float $x1, float $y1, float $x2, float $y2, bool $arrow, string $style): string
    {
        $d = sprintf('M%s %sL%s %s', self::num($x1), self::num($y1), self::num($x2), self::num($y2));
        if ($arrow) {
            // Pfeilspitze am Endpunkt, zwei Schenkel im 30°-Winkel
            $angle = atan2($y2 - $y1, $x2 - $x1);
            $len = min(1.8, max(0.8, hypot($x2 - $x1, $y2 - $y1) * 0.3));
            foreach ([M_PI / 6, -M_PI / 6] as $spread) {
                $d .= sprintf(
                    'M%s %sL%s %s',
                    self::num($x2 - $len * cos($angle - $spread)),
                    self::num($y2 - $len * sin($angle - $spread)),
                    self::num($x2),
                    self::num($y2),
                );
            }
        }

        return '<path d="' . $d . '" fill="none" stroke="currentColor" stroke-width="' . self::sw(0.9) . '" stroke-linecap="round" stroke-linejoin="round"' . self::strokeOpacity($style) . '/>';
    }

    private static function renderCircle(float $x, float $y, float $w, float $h, string $style): string
    {
        return sprintf(
            '<ellipse cx="%s" cy="%s" rx="%s" ry="%s" %s/>',
            self::num($x + $w / 2),
            self::num($y + $h / 2),
            self::num($w / 2),
            self::num($h / 2),
            self::paint($style),
        );
    }

    private static function renderButton(float $x, float $y, float $w, float $h, string $style): string
    {
        $svg = sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" %s/>',
            self::num($x),
            self::num($y),
            self::num($w),
            self::num($h),
            self::num(min($h, $w) / 2),
            self::paint($style),
        );
        // Beschriftung als kurzer Strich in der Mitte (bei voller Fläche ausgespart)
        if ($w >= 3 && 'solid' !== $style && 'icon' === self::$kind) {
            $svg .= sprintf(
                '<path d="M%s %sh%s" stroke="currentColor" stroke-width="' . self::sw(0.7) . '" stroke-linecap="round"/>',
                self::num($x + $w * 0.3),
                self::num($y + $h / 2),
                self::num($w * 0.4),
            );
        }

        return $svg;
    }

    private static function renderHeading(float $x, float $y, float $w, float $h, string $style): string
    {
        // kräftiger Balken oben, schmalere Unterzeile darunter (wenn Platz ist)
        $barH = $h >= 2.5 ? $h * 0.55 : $h;
        $svg = sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" %s/>',
            self::num($x),
            self::num($y),
            self::num($w),
            self::num($barH),
            self::num(min(0.8, $barH / 2)),
            self::paint($style),
        );
        if ($h >= 2.5) {
            $svg .= sprintf(
                '<path d="M%s %sh%s" stroke="currentColor" stroke-width="' . self::sw(0.7) . '" stroke-linecap="round" stroke-opacity="0.55"/>',
                self::num($x + 0.35),
                self::num($y + $h * 0.85),
                self::num($w * 0.6),
            );
        }

        return $svg;
    }

    private static function renderStar(float $x, float $y, float $w, float $h, string $style): string
    {
        $cx = $x + $w / 2;
        $cy = $y + $h / 2 + $h * 0.04;
        $rx = $w / 2;
        $ry = $h / 2;
        $points = [];
        for ($i = 0; $i < 10; ++$i) {
            $f = 0 === $i % 2 ? 1.0 : 0.45;
            $a = -M_PI / 2 + $i * M_PI / 5;
            $points[] = self::num($cx + cos($a) * $rx * $f) . ',' . self::num($cy + sin($a) * $ry * $f);
        }

        return '<polygon points="' . implode(' ', $points) . '" ' . self::paint($style) . ' stroke-linejoin="round"/>';
    }

    private static function renderPin(float $x, float $y, float $w, float $h, string $style): string
    {
        // Kartenmarker: Kreis oben, Spitze unten mittig
        $cx = $x + $w / 2;
        $r = min($w / 2, $h * 0.38);
        $cy = $y + $r;
        $tipY = $y + $h;
        $svg = sprintf(
            '<path d="M%s %sC%s %s %s %s %s %sA%s %s 0 1 1 %s %sC%s %s %s %s %s %sZ" %s stroke-linejoin="round"/>',
            self::num($cx),
            self::num($tipY),
            self::num($cx - $r * 0.35),
            self::num($tipY - ($tipY - $cy) * 0.35),
            self::num($cx - $r),
            self::num($cy + $r * 0.9),
            self::num($cx - $r),
            self::num($cy),
            self::num($r),
            self::num($r),
            self::num($cx + $r),
            self::num($cy),
            self::num($cx + $r),
            self::num($cy + $r * 0.9),
            self::num($cx + $r * 0.35),
            self::num($tipY - ($tipY - $cy) * 0.35),
            self::num($cx),
            self::num($tipY),
            self::paint($style),
        );
        $svg .= sprintf(
            '<circle cx="%s" cy="%s" r="%s" fill="%s"/>',
            self::num($cx),
            self::num($cy),
            self::num(max(0.3, $r * 0.38)),
            'solid' === $style ? '#fff' : 'currentColor',
        );

        return $svg;
    }

    private static function renderCheck(float $x, float $y, float $w, float $h, string $style): string
    {
        // Kreis mit Haken
        $svg = self::renderCircle($x, $y, $w, $h, $style);
        $svg .= sprintf(
            '<path d="M%s %sL%s %sL%s %s" fill="none" stroke="%s" stroke-width="' . self::sw(0.8) . '" stroke-linecap="round" stroke-linejoin="round"/>',
            self::num($x + $w * 0.28),
            self::num($y + $h * 0.52),
            self::num($x + $w * 0.44),
            self::num($y + $h * 0.68),
            self::num($x + $w * 0.72),
            self::num($y + $h * 0.36),
            'solid' === $style ? '#fff' : 'currentColor',
        );

        return $svg;
    }

    private static function renderBrowser(float $x, float $y, float $w, float $h, string $style): string
    {
        // Browserfenster: Rahmen, Titelleiste mit drei Punkten und Adresszeile
        $bar = min($h * 0.2, 'preview' === self::$kind ? 1.6 : 2.4);
        $svg = self::rect($x, $y, $w, $h, self::paint($style));
        $svg .= sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="0.6" fill="none" stroke="currentColor" stroke-width="' . self::sw(0.6) . '" stroke-opacity="0.35"/>', self::num($x), self::num($y), self::num($w), self::num($h));
        $svg .= sprintf('<path d="M%s %sh%s" stroke="currentColor" stroke-width="' . self::sw(0.4) . '" stroke-opacity="0.3"/>', self::num($x), self::num($y + $bar), self::num($w));
        $r = $bar * 0.16;
        for ($i = 0; $i < 3; ++$i) {
            $svg .= sprintf('<circle cx="%s" cy="%s" r="%s" fill="currentColor" opacity="0.4"/>', self::num($x + $bar * 0.6 + $i * $r * 3.4), self::num($y + $bar / 2), self::num($r));
        }
        $svg .= sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="currentColor" opacity="0.15"/>', self::num($x + $bar * 0.6 + $r * 10), self::num($y + $bar * 0.36), self::num(min($w * 0.3, 9)), self::num($bar * 0.28), self::num($bar * 0.14));

        return $svg;
    }

    private static function renderText(float $x, float $y, float $w, float $h, string $style): string
    {
        if ('preview' === self::$kind) {
            // Vorschaubild: Zeilen im festen Abstand, so viele wie in die Höhe passen
            $gap = 0.9;
            $lines = max(1, min(14, (int) floor(($h - 0.2) / $gap) + 1));
            $svg = '';
            for ($i = 0; $i < $lines; ++$i) {
                $svg .= sprintf(
                    '<path d="M%s %sh%s" stroke="currentColor" stroke-width="' . self::sw(0.9) . '" stroke-linecap="round"%s/>',
                    self::num($x + 0.2),
                    self::num($y + 0.2 + $gap * $i),
                    self::num(max(0.1, $w * ($i === $lines - 1 && $lines > 1 ? 0.62 : 1) - 0.4)),
                    self::strokeOpacity($style),
                );
            }

            return $svg;
        }
        $lines = 3;
        $gap = $h / ($lines + 0.5);
        $svg = '';
        for ($i = 0; $i < $lines; ++$i) {
            $lineY = $y + $gap * ($i + 0.7);
            $lineW = $w * (2 === $i ? 0.6 : 1);
            $svg .= sprintf(
                '<path d="M%s %sh%s" stroke="currentColor" stroke-width="' . self::sw(0.9) . '" stroke-linecap="round"%s/>',
                self::num($x),
                self::num($lineY),
                self::num($lineW),
                self::strokeOpacity($style),
            );
        }

        return $svg;
    }
}
