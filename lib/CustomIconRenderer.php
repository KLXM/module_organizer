<?php

namespace KLXM\ModuleOrganizer;

/**
 * Baut aus einer vom Icon-Editor (assets/module_organizer_icon_editor.js)
 * gelieferten Rechteck-Liste serverseitig valides SVG-Markup - der Client
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
    public const MAX_SHAPES = 8;

    /** @return list<string> */
    public static function getValidTypes(): array
    {
        return ['text', 'image', 'video', 'media', 'form', 'rect', 'line'];
    }

    /**
     * @param mixed $shapes rohe, noch ungeprüfte Client-Eingabe (json_decode-Ergebnis)
     * @return list<array{x: float, y: float, w: float, h: float, type: string, x2?: float, y2?: float}>
     */
    public static function sanitizeShapes($shapes): array
    {
        if (!is_array($shapes)) {
            return [];
        }

        $validTypes = self::getValidTypes();
        $result = [];

        foreach ($shapes as $shape) {
            if (count($result) >= self::MAX_SHAPES) {
                break;
            }
            if (!is_array($shape)) {
                continue;
            }

            $type = is_string($shape['type'] ?? null) ? $shape['type'] : '';
            if (!in_array($type, $validTypes, true)) {
                continue;
            }

            // "line" ist eine freie Strecke zwischen zwei Punkten statt einer
            // Box - eigene, einfachere Validierung (nur Punkte in Canvas-
            // Grenzen klemmen, kein Mindest-Rechteck noetig).
            if ('line' === $type) {
                $x = self::clampFloat($shape['x'] ?? 0, 0, self::CANVAS_WIDTH);
                $y = self::clampFloat($shape['y'] ?? 0, 0, self::CANVAS_HEIGHT);
                $x2 = self::clampFloat($shape['x2'] ?? 0, 0, self::CANVAS_WIDTH);
                $y2 = self::clampFloat($shape['y2'] ?? 0, 0, self::CANVAS_HEIGHT);
                if (abs($x2 - $x) < 0.2 && abs($y2 - $y) < 0.2) {
                    continue;
                }
                $result[] = ['x' => $x, 'y' => $y, 'x2' => $x2, 'y2' => $y2, 'w' => 0.0, 'h' => 0.0, 'type' => $type];
                continue;
            }

            $x = self::clampFloat($shape['x'] ?? 0, 0, self::CANVAS_WIDTH);
            $y = self::clampFloat($shape['y'] ?? 0, 0, self::CANVAS_HEIGHT);
            $w = self::clampFloat($shape['w'] ?? 0, 0.5, self::CANVAS_WIDTH);
            $h = self::clampFloat($shape['h'] ?? 0, 0.5, self::CANVAS_HEIGHT);

            // Rechteck darf nicht ueber den Canvas-Rand hinausragen.
            $w = min($w, self::CANVAS_WIDTH - $x);
            $h = min($h, self::CANVAS_HEIGHT - $y);
            if ($w < 0.5 || $h < 0.5) {
                continue;
            }

            $result[] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'type' => $type];
        }

        return $result;
    }

    private static function clampFloat(mixed $value, float $min, float $max): float
    {
        $float = is_numeric($value) ? (float) $value : 0.0;

        return max($min, min($max, $float));
    }

    /**
     * @param list<array{x: float, y: float, w: float, h: float, type: string, x2?: float, y2?: float}> $shapes
     */
    public static function render(array $shapes): string
    {
        $body = '';
        foreach ($shapes as $shape) {
            $body .= self::renderShape($shape);
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . self::CANVAS_WIDTH . ' ' . self::CANVAS_HEIGHT
            . '" role="img" aria-hidden="true">' . $body . '</svg>';
    }

    /**
     * @param array{x: float, y: float, w: float, h: float, type: string, x2?: float, y2?: float} $shape
     */
    private static function renderShape(array $shape): string
    {
        if ('line' === $shape['type']) {
            return self::renderLine($shape['x'], $shape['y'], $shape['x2'] ?? $shape['x'], $shape['y2'] ?? $shape['y']);
        }

        $x = $shape['x'];
        $y = $shape['y'];
        $w = $shape['w'];
        $h = $shape['h'];

        return match ($shape['type']) {
            'image' => self::renderImage($x, $y, $w, $h),
            'video' => self::renderVideo($x, $y, $w, $h),
            'media' => self::renderMedia($x, $y, $w, $h),
            'form' => self::renderForm($x, $y, $w, $h),
            'rect' => self::renderRect($x, $y, $w, $h),
            'text' => self::renderText($x, $y, $w, $h),
            default => self::renderText($x, $y, $w, $h),
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

    private static function renderImage(float $x, float $y, float $w, float $h): string
    {
        $svg = self::rect($x, $y, $w, $h, 'fill="currentColor" fill-opacity="0.12" stroke="currentColor" stroke-width="0.6"');
        $cx = $x + $w * 0.28;
        $cy = $y + $h * 0.3;
        $r = min($w, $h) * 0.12;
        $svg .= sprintf('<circle cx="%s" cy="%s" r="%s" fill="currentColor"/>', self::num($cx), self::num($cy), self::num(max(0.3, $r)));
        $svg .= sprintf(
            '<path d="M%s %sl%s -%sa1 1 0 0 1 1.4 0l%s %s" fill="none" stroke="currentColor" stroke-width="0.6"/>',
            self::num($x + $w * 0.08),
            self::num($y + $h * 0.85),
            self::num($w * 0.3),
            self::num($h * 0.3),
            self::num($w * 0.32),
            self::num($h * 0.32),
        );

        return $svg;
    }

    private static function renderVideo(float $x, float $y, float $w, float $h): string
    {
        $svg = self::rect($x, $y, $w, $h, 'fill="currentColor" fill-opacity="0.12" stroke="currentColor" stroke-width="0.6"');
        $cx = $x + $w / 2;
        $cy = $y + $h / 2;
        $r = min($w, $h) * 0.32;
        $svg .= sprintf('<circle cx="%s" cy="%s" r="%s" fill="currentColor" fill-opacity="0.18"/>', self::num($cx), self::num($cy), self::num($r));
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
            '<path d="M%s %sL%s %sL%s %sZ" fill="currentColor"/>',
            self::num($baseX),
            self::num($cy - $triHalfBase),
            self::num($baseX),
            self::num($cy + $triHalfBase),
            self::num($tipX),
            self::num($cy),
        );

        return $svg;
    }

    private static function renderMedia(float $x, float $y, float $w, float $h): string
    {
        // Dokument mit umgeknickter Ecke oben rechts.
        $fold = min($w, $h) * 0.28;
        $svg = sprintf(
            '<path d="M%s %sh%sl%s %sv%sh-%sz" fill="currentColor" fill-opacity="0.12" stroke="currentColor" stroke-width="0.6" stroke-linejoin="round"/>',
            self::num($x),
            self::num($y),
            self::num($w - $fold),
            self::num($fold),
            self::num($fold),
            self::num($h - $fold),
            self::num($w),
        );
        $svg .= sprintf(
            '<path d="M%s %sv%sh%s" fill="none" stroke="currentColor" stroke-width="0.5"/>',
            self::num($x + $w - $fold),
            self::num($y),
            self::num($fold),
            self::num($fold),
        );
        $lineY = $y + $h * 0.55;
        $svg .= sprintf(
            '<path d="M%s %sh%sM%s %sh%s" stroke="currentColor" stroke-width="0.6" stroke-linecap="round"/>',
            self::num($x + $w * 0.15),
            self::num($lineY),
            self::num($w * 0.7),
            self::num($x + $w * 0.15),
            self::num($lineY + $h * 0.18),
            self::num($w * 0.5),
        );

        return $svg;
    }

    private static function renderForm(float $x, float $y, float $w, float $h): string
    {
        $lineH = $h * 0.3;
        $svg = self::rect($x, $y, $w, $lineH, 'fill="currentColor" fill-opacity="0.12" stroke="currentColor" stroke-width="0.6"');
        $svg .= self::rect($x, $y + $h * 0.55, $w * 0.6, $lineH, 'fill="currentColor" fill-opacity="0.12" stroke="currentColor" stroke-width="0.6"');
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

    private static function renderRect(float $x, float $y, float $w, float $h): string
    {
        return self::rect($x, $y, $w, $h, 'fill="currentColor" fill-opacity="0.12" stroke="currentColor" stroke-width="0.6"');
    }

    private static function renderLine(float $x1, float $y1, float $x2, float $y2): string
    {
        return sprintf(
            '<path d="M%s %sL%s %s" fill="none" stroke="currentColor" stroke-width="0.9" stroke-linecap="round"/>',
            self::num($x1),
            self::num($y1),
            self::num($x2),
            self::num($y2),
        );
    }

    private static function renderText(float $x, float $y, float $w, float $h): string
    {
        $lines = 3;
        $gap = $h / ($lines + 0.5);
        $svg = '';
        for ($i = 0; $i < $lines; ++$i) {
            $lineY = $y + $gap * ($i + 0.7);
            $lineW = $w * (2 === $i ? 0.6 : 1);
            $svg .= sprintf(
                '<path d="M%s %sh%s" stroke="currentColor" stroke-width="0.9" stroke-linecap="round"/>',
                self::num($x),
                self::num($lineY),
                self::num($lineW),
            );
        }

        return $svg;
    }
}
