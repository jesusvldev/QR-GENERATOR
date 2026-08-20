<?php

namespace Sello;

final class Drawer
{
    public static function markup(array $modules, array $opts)
    {
        $margin = $opts['margin'];
        $n = count($modules);
        $dim = $n + $margin * 2;
        $size = $opts['size'];
        $path = self::path($modules, $opts['moduleStyle']);
        $bg = htmlspecialchars($opts['background'], ENT_QUOTES, 'UTF-8');
        $color = htmlspecialchars($opts['color'], ENT_QUOTES, 'UTF-8');
        $shape = $opts['moduleStyle'] === 'square' ? 'crispEdges' : 'geometricPrecision';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim .
            '" width="' . $size . '" height="' . $size . '" shape-rendering="' . $shape . '">' .
            '<rect width="100%" height="100%" fill="' . $bg . '"/>' .
            '<g transform="translate(' . $margin . ',' . $margin . ')" fill="' . $color . '">' .
            '<path d="' . $path . '"/></g></svg>';
    }

    public static function bitmap(array $modules, array $opts)
    {
        $margin = (int) $opts['margin'];
        $n = count($modules);
        $dim = $n + $margin * 2;
        $scale = max(1, (int) floor($opts['size'] / $dim));
        $width = $dim * $scale;
        $ink = self::rgb($opts['color']);
        $paper = self::rgb($opts['background']);
        $style = $opts['moduleStyle'];
        $raw = '';
        for ($y = 0; $y < $width; $y++) {
            $raw .= "\x00";
            $row = intdiv($y, $scale) - $margin;
            for ($x = 0; $x < $width; $x++) {
                $col = intdiv($x, $scale) - $margin;
                $lit = $row >= 0 && $col >= 0 && $row < $n && $col < $n && !empty($modules[$row][$col]);
                if ($lit && $style === 'dots') {
                    $cx = ($col + $margin + 0.5) * $scale;
                    $cy = ($row + $margin + 0.5) * $scale;
                    $dx = $x + 0.5 - $cx;
                    $dy = $y + 0.5 - $cy;
                    $lit = ($dx * $dx + $dy * $dy) <= pow(0.38 * $scale, 2);
                }
                $c = $lit ? $ink : $paper;
                $raw .= chr($c[0]) . chr($c[1]) . chr($c[2]);
            }
        }
        $ihdr = pack('NN', $width, $width) . "\x08\x02\x00\x00\x00";
        return "\x89PNG\r\n\x1a\n" .
            self::chunk('IHDR', $ihdr) .
            self::chunk('IDAT', gzcompress($raw, 9)) .
            self::chunk('IEND', '');
    }

    private static function filled(array $modules, $r, $c)
    {
        return isset($modules[$r][$c]) && $modules[$r][$c];
    }

    private static function path(array $modules, $style)
    {
        $n = count($modules);
        $parts = [];
        $radius = $style === 'smooth' ? 0.5 : 0.22;
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if (empty($modules[$r][$c])) {
                    continue;
                }
                if ($style === 'dots') {
                    $parts[] = 'M' . ($c + 0.5) . ',' . ($r + 0.12) . ' a0.38,0.38 0 1,0 0,0.76 a0.38,0.38 0 1,0 0,-0.76';
                    continue;
                }
                if ($style === 'square') {
                    $parts[] = 'M' . $c . ',' . $r . 'h1v1h-1z';
                    continue;
                }
                $t = self::filled($modules, $r - 1, $c);
                $b = self::filled($modules, $r + 1, $c);
                $l = self::filled($modules, $r, $c - 1);
                $ri = self::filled($modules, $r, $c + 1);
                $tl = !($t || $l);
                $tr = !($t || $ri);
                $bl = !($b || $l);
                $br = !($b || $ri);
                $rad = $style === 'smooth' ? $radius : 0.28;
                $x = $c;
                $y = $r;
                $d = 'M' . ($x + ($tl ? $rad : 0)) . ',' . $y;
                $d .= 'H' . ($x + 1 - ($tr ? $rad : 0));
                $d .= $tr ? ('A' . $rad . ',' . $rad . ' 0 0 1 ' . ($x + 1) . ',' . ($y + $rad)) : ('H' . ($x + 1));
                $d .= 'V' . ($y + 1 - ($br ? $rad : 0));
                $d .= $br ? ('A' . $rad . ',' . $rad . ' 0 0 1 ' . ($x + 1 - $rad) . ',' . ($y + 1)) : ('V' . ($y + 1));
                $d .= 'H' . ($x + ($bl ? $rad : 0));
                $d .= $bl ? ('A' . $rad . ',' . $rad . ' 0 0 1 ' . $x . ',' . ($y + 1 - $rad)) : ('H' . $x);
                $d .= 'V' . ($y + ($tl ? $rad : 0));
                $d .= $tl ? ('A' . $rad . ',' . $rad . ' 0 0 1 ' . ($x + $rad) . ',' . $y) : ('V' . $y);
                $d .= 'z';
                $parts[] = $d;
            }
        }
        return implode('', $parts);
    }

    private static function rgb($value)
    {
        $hex = ltrim((string) $value, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function chunk($type, $data)
    {
        return pack('N', strlen($data)) . $type . $data . hash('crc32b', $type . $data, true);
    }
}
