<?php

namespace Sello;

/**
 * Encoder interno. Usa Sello::create() o QR::create().
 *
 * @internal
 */
final class Encoder
{
    private const LEVELS = ['L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3];
    private const LEVEL_BITS = [1, 0, 3, 2];
    private const ALPHA = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    private static $exp = null;
    private static $log = null;
    private static $genCache = [];

    public static function encode($text, array $options)
    {
        $levelName = strtoupper((string) ($options['errorCorrection'] ?? 'M'));
        if (!isset(self::LEVELS[$levelName])) {
            throw new \InvalidArgumentException('Nivel de corrección inválido. Usa L, M, Q o H.');
        }
        $level = self::LEVELS[$levelName];
        $mode = $options['mode'] ?? self::chooseMode($text);
        $version = self::chooseVersion($text, $mode, $level, $options['version'] ?? null);
        $bits = self::buildData($text, $mode, $version, $level);
        $n = $version * 4 + 17;
        $grid = [];
        $reserved = [];
        for ($i = 0; $i < $n; $i++) {
            $grid[] = array_fill(0, $n, 0);
            $reserved[] = array_fill(0, $n, 0);
        }
        self::placeFunction($grid, $reserved, $version);
        self::placeData($grid, $reserved, $bits);

        $bestMask = 0;
        $bestScore = PHP_INT_MAX;
        $best = null;
        $forced = array_key_exists('mask', $options) ? $options['mask'] : null;
        $start = $forced === null ? 0 : (int) $forced;
        $end = $forced === null ? 7 : (int) $forced;
        for ($mask = $start; $mask <= $end; $mask++) {
            $candidate = self::cloneGrid($grid);
            self::applyMask($candidate, $reserved, $mask);
            self::drawFormat($candidate, $level, $mask);
            self::drawVersion($candidate, $version);
            $score = self::penalty($candidate);
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask = $mask;
                $best = $candidate;
            }
        }

        return [
            'modules' => $best,
            'version' => $version,
            'mode' => $mode,
            'errorCorrection' => $levelName,
            'mask' => $bestMask,
        ];
    }

    private static function ecc()
    {
        static $ecc = null;
        if ($ecc !== null) {
            return $ecc;
        }
        $ecc = [
            null,
            [[7,1,19,0,0],[10,1,16,0,0],[13,1,13,0,0],[17,1,9,0,0]],
            [[10,1,34,0,0],[16,1,28,0,0],[22,1,22,0,0],[28,1,16,0,0]],
            [[15,1,55,0,0],[26,1,44,0,0],[18,2,17,0,0],[22,2,13,0,0]],
            [[20,1,80,0,0],[18,2,32,0,0],[26,2,24,0,0],[16,4,9,0,0]],
            [[26,1,108,0,0],[24,2,43,0,0],[18,2,15,2,16],[22,2,11,2,12]],
            [[18,2,68,0,0],[16,4,27,0,0],[24,4,19,0,0],[28,4,15,0,0]],
            [[20,2,78,0,0],[18,4,31,0,0],[18,2,14,4,15],[26,4,13,1,14]],
            [[24,2,97,0,0],[22,2,38,2,39],[22,4,18,2,19],[26,4,14,2,15]],
            [[30,2,116,0,0],[22,3,36,2,37],[20,4,16,4,17],[24,4,12,4,13]],
            [[18,2,68,2,69],[26,4,43,1,44],[24,6,19,2,20],[28,6,15,2,16]],
            [[20,4,81,0,0],[30,1,50,4,51],[28,4,22,4,23],[24,3,12,8,13]],
            [[24,2,92,2,93],[22,6,36,2,37],[26,4,20,6,21],[28,7,14,4,15]],
            [[26,4,107,0,0],[22,8,37,1,38],[24,8,20,4,21],[22,12,11,4,12]],
            [[30,3,115,1,116],[24,4,40,5,41],[20,11,16,5,17],[24,11,12,5,13]],
            [[22,5,87,1,88],[24,5,41,5,42],[30,5,24,7,25],[24,11,12,7,13]],
            [[24,5,98,1,99],[28,7,45,3,46],[24,15,19,2,20],[30,3,15,13,16]],
            [[28,1,107,5,108],[28,10,46,1,47],[28,1,22,15,23],[28,2,14,17,15]],
            [[30,5,120,1,121],[26,9,43,4,44],[28,17,22,1,23],[28,2,14,19,15]],
            [[28,3,113,4,114],[26,3,44,11,45],[26,17,21,4,22],[26,9,13,16,14]],
            [[28,3,107,5,108],[26,3,41,13,42],[30,15,24,5,25],[28,15,15,10,16]],
            [[28,4,116,4,117],[26,17,42,0,0],[28,17,22,6,23],[30,19,16,6,17]],
            [[28,2,111,7,112],[28,17,46,0,0],[30,7,24,16,25],[24,34,13,0,0]],
            [[30,4,121,5,122],[28,4,47,14,48],[30,11,24,14,25],[30,16,15,14,16]],
            [[30,6,117,4,118],[28,6,45,14,46],[30,11,24,16,25],[30,30,16,2,17]],
            [[26,8,106,4,107],[28,8,47,13,48],[30,7,24,22,25],[30,22,15,13,16]],
            [[28,10,114,2,115],[28,19,46,4,47],[28,28,22,6,23],[30,33,16,4,17]],
            [[30,8,122,4,123],[28,22,45,3,46],[30,8,23,26,24],[30,12,15,28,16]],
            [[30,3,117,10,118],[28,3,45,23,46],[30,4,24,31,25],[30,11,15,31,16]],
            [[30,7,116,7,117],[28,21,45,7,46],[30,1,23,37,24],[30,19,15,26,16]],
            [[30,5,115,10,116],[28,19,47,10,48],[30,15,24,25,25],[30,23,15,25,16]],
            [[30,13,115,3,116],[28,2,46,29,47],[30,42,24,1,25],[30,23,15,28,16]],
            [[30,17,115,0,0],[28,10,46,23,47],[30,10,24,35,25],[30,19,15,35,16]],
            [[30,17,115,1,116],[28,14,46,21,47],[30,29,24,19,25],[30,11,15,46,16]],
            [[30,13,115,6,116],[28,14,46,23,47],[30,44,24,7,25],[30,59,16,1,17]],
            [[30,12,121,7,122],[28,12,47,26,48],[30,39,24,14,25],[30,22,15,41,16]],
            [[30,6,121,14,122],[28,6,47,34,48],[30,46,24,10,25],[30,2,15,64,16]],
            [[30,17,122,4,123],[28,29,46,14,47],[30,49,24,10,25],[30,24,15,46,16]],
            [[30,4,122,18,123],[28,13,46,32,47],[30,48,24,14,25],[30,42,15,32,16]],
            [[30,20,117,4,118],[28,40,47,7,48],[30,43,24,22,25],[30,10,15,67,16]],
            [[30,19,118,6,119],[28,18,47,31,48],[30,34,24,34,25],[30,20,15,61,16]],
        ];
        return $ecc;
    }

    private static function align()
    {
        return [
            [], [], [18], [22], [26], [30], [34], [22, 38], [24, 42], [26, 46],
            [28, 50], [30, 54], [32, 58], [34, 62], [26, 46, 66], [26, 48, 70],
            [26, 50, 74], [30, 54, 78], [30, 56, 82], [30, 58, 86], [34, 62, 90],
            [28, 50, 72, 94], [26, 50, 74, 98], [30, 54, 78, 102], [28, 54, 80, 106],
            [32, 58, 84, 110], [30, 58, 86, 114], [34, 62, 90, 118],
            [26, 50, 74, 98, 122], [30, 54, 78, 102, 126], [26, 52, 78, 104, 130],
            [30, 56, 82, 108, 134], [34, 60, 86, 112, 138], [30, 58, 86, 114, 142],
            [34, 62, 90, 118, 146], [30, 54, 78, 102, 126, 150],
            [24, 50, 76, 102, 128, 154], [28, 54, 80, 106, 132, 158],
            [32, 58, 84, 110, 136, 162], [26, 54, 82, 110, 138, 166],
            [30, 58, 86, 114, 142, 170],
        ];
    }

    private static function remainder()
    {
        return [
            0, 0, 7, 7, 7, 7, 7, 0, 0, 0, 0, 0, 0, 0, 3, 3, 3, 3, 3, 3, 3,
            4, 4, 4, 4, 4, 4, 4, 3, 3, 3, 3, 3, 3, 3, 0, 0, 0, 0, 0, 0,
        ];
    }

    private static function initGf()
    {
        if (self::$exp !== null) {
            return;
        }
        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11d;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function gfMul($a, $b)
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        self::initGf();
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    private static function generator($degree)
    {
        if (isset(self::$genCache[$degree])) {
            return self::$genCache[$degree];
        }
        self::initGf();
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $coef = self::$exp[$i];
            $next = array_fill(0, count($poly) + 1, 0);
            $next[0] = $poly[0];
            $next[count($poly)] = self::gfMul($poly[count($poly) - 1], $coef);
            for ($j = 1; $j < count($poly); $j++) {
                $next[$j] = $poly[$j] ^ self::gfMul($poly[$j - 1], $coef);
            }
            $poly = $next;
        }
        self::$genCache[$degree] = array_slice($poly, 1);
        return self::$genCache[$degree];
    }

    private static function rsEncode(array $data, $ecCount)
    {
        $gen = self::generator($ecCount);
        $ecc = array_fill(0, $ecCount, 0);
        $len = count($data);
        for ($i = 0; $i < $len; $i++) {
            $factor = $data[$i] ^ $ecc[0];
            for ($j = 0; $j < $ecCount - 1; $j++) {
                $ecc[$j] = $ecc[$j + 1];
            }
            $ecc[$ecCount - 1] = 0;
            if ($factor === 0) {
                continue;
            }
            for ($j = 0; $j < $ecCount; $j++) {
                $ecc[$j] ^= self::gfMul($gen[$j], $factor);
            }
        }
        return $ecc;
    }

    private static function utf8Bytes($text)
    {
        return array_values(unpack('C*', $text));
    }

    private static function chooseMode($text)
    {
        if (preg_match('/^[0-9]*$/', $text)) {
            return 'numeric';
        }
        if (preg_match('/^[0-9A-Z $%*+\-.\/:]*$/', $text)) {
            return 'alphanumeric';
        }
        return 'byte';
    }

    private static function countBits($mode, $version)
    {
        if ($mode === 'numeric') {
            return $version < 10 ? 10 : ($version < 27 ? 12 : 14);
        }
        if ($mode === 'alphanumeric') {
            return $version < 10 ? 9 : ($version < 27 ? 11 : 13);
        }
        return $version < 10 ? 8 : 16;
    }

    private static function payloadBits($text, $mode)
    {
        $len = strlen($text);
        if ($mode === 'numeric') {
            $n = 0;
            for ($i = 0; $i < $len; $i += 3) {
                $g = $len - $i;
                $n += $g >= 3 ? 10 : ($g === 2 ? 7 : 4);
            }
            return $n;
        }
        if ($mode === 'alphanumeric') {
            return intdiv($len, 2) * 11 + ($len % 2) * 6;
        }
        return count(self::utf8Bytes($text)) * 8;
    }

    private static function charCount($text, $mode)
    {
        return $mode === 'byte' ? count(self::utf8Bytes($text)) : strlen($text);
    }

    private static function dataCapacity($version, $level)
    {
        $spec = self::ecc()[$version][$level];
        return $spec[1] * $spec[2] + $spec[3] * $spec[4];
    }

    private static function totalBits($text, $mode, $version)
    {
        return 4 + self::countBits($mode, $version) + self::payloadBits($text, $mode);
    }

    private static function chooseVersion($text, $mode, $level, $forced)
    {
        if ($forced) {
            $forced = (int) $forced;
            if ($forced < 1 || $forced > 40) {
                throw new \InvalidArgumentException('La versión QR debe estar entre 1 y 40.');
            }
            if (self::totalBits($text, $mode, $forced) > self::dataCapacity($forced, $level) * 8) {
                throw new \InvalidArgumentException('El texto no cabe en la versión ' . $forced . '.');
            }
            return $forced;
        }
        for ($v = 1; $v <= 40; $v++) {
            if (self::totalBits($text, $mode, $v) <= self::dataCapacity($v, $level) * 8) {
                return $v;
            }
        }
        throw new \InvalidArgumentException('El texto es demasiado largo para un código QR.');
    }

    private static function put(array &$bits, $value, $len)
    {
        for ($i = $len - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function encodePayload(array &$bits, $text, $mode)
    {
        $len = strlen($text);
        if ($mode === 'numeric') {
            for ($i = 0; $i < $len; $i += 3) {
                $g = substr($text, $i, 3);
                $gl = strlen($g);
                self::put($bits, (int) $g, $gl === 3 ? 10 : ($gl === 2 ? 7 : 4));
            }
            return;
        }
        if ($mode === 'alphanumeric') {
            for ($i = 0; $i < $len; $i += 2) {
                if ($i + 1 < $len) {
                    self::put($bits, strpos(self::ALPHA, $text[$i]) * 45 + strpos(self::ALPHA, $text[$i + 1]), 11);
                } else {
                    self::put($bits, strpos(self::ALPHA, $text[$i]), 6);
                }
            }
            return;
        }
        foreach (self::utf8Bytes($text) as $byte) {
            self::put($bits, $byte, 8);
        }
    }

    private static function buildData($text, $mode, $version, $level)
    {
        $spec = self::ecc()[$version][$level];
        $capacity = self::dataCapacity($version, $level);
        $bits = [];
        $modes = ['numeric' => 1, 'alphanumeric' => 2, 'byte' => 4];
        self::put($bits, $modes[$mode], 4);
        self::put($bits, self::charCount($text, $mode), self::countBits($mode, $version));
        self::encodePayload($bits, $text, $mode);

        $limit = $capacity * 8;
        $terminator = min(4, $limit - count($bits));
        self::put($bits, 0, $terminator);
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $bytes = [];
        $bitLen = count($bits);
        for ($i = 0; $i < $bitLen; $i += 8) {
            $v = 0;
            for ($b = 0; $b < 8; $b++) {
                $v = ($v << 1) | $bits[$i + $b];
            }
            $bytes[] = $v;
        }
        $p = 0;
        $pads = [0xec, 0x11];
        while (count($bytes) < $capacity) {
            $bytes[] = $pads[$p++ % 2];
        }

        $blocks = [];
        $offset = 0;
        $take = function ($n) use (&$bytes, &$offset, &$blocks, $spec) {
            $chunk = array_slice($bytes, $offset, $n);
            $offset += $n;
            $blocks[] = ['data' => $chunk, 'ecc' => self::rsEncode($chunk, $spec[0])];
        };
        for ($i = 0; $i < $spec[1]; $i++) {
            $take($spec[2]);
        }
        for ($i = 0; $i < $spec[3]; $i++) {
            $take($spec[4]);
        }

        $interleaved = [];
        $maxData = $spec[3] ? $spec[4] : $spec[2];
        $blockCount = count($blocks);
        for ($i = 0; $i < $maxData; $i++) {
            for ($b = 0; $b < $blockCount; $b++) {
                if ($i < count($blocks[$b]['data'])) {
                    $interleaved[] = $blocks[$b]['data'][$i];
                }
            }
        }
        for ($i = 0; $i < $spec[0]; $i++) {
            for ($b = 0; $b < $blockCount; $b++) {
                $interleaved[] = $blocks[$b]['ecc'][$i];
            }
        }

        $out = [];
        foreach ($interleaved as $byte) {
            for ($b = 7; $b >= 0; $b--) {
                $out[] = ($byte >> $b) & 1;
            }
        }
        $rem = self::remainder();
        for ($i = 0; $i < $rem[$version]; $i++) {
            $out[] = 0;
        }
        return $out;
    }

    private static function drawFinder(array &$grid, array &$reserved, $x, $y)
    {
        $n = count($grid);
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $y + $r;
                $cc = $x + $c;
                if ($rr < 0 || $cc < 0 || $rr >= $n || $cc >= $n) {
                    continue;
                }
                $on = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6 &&
                    ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                $grid[$rr][$cc] = $on ? 1 : 0;
                $reserved[$rr][$cc] = 1;
            }
        }
    }

    private static function drawAlignment(array &$grid, array &$reserved, $cx, $cy)
    {
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                $grid[$cy + $r][$cx + $c] = (($r === 0 && $c === 0) || abs($r) === 2 || abs($c) === 2) ? 1 : 0;
                $reserved[$cy + $r][$cx + $c] = 1;
            }
        }
    }

    private static function placeFunction(array &$grid, array &$reserved, $version)
    {
        $n = count($grid);
        self::drawFinder($grid, $reserved, 0, 0);
        self::drawFinder($grid, $reserved, $n - 7, 0);
        self::drawFinder($grid, $reserved, 0, $n - 7);

        $positions = array_merge([6], self::align()[$version]);
        $pc = count($positions);
        for ($i = 0; $i < $pc; $i++) {
            for ($j = 0; $j < $pc; $j++) {
                $r = $positions[$i];
                $c = $positions[$j];
                if (($r <= 8 && $c <= 8) || ($r <= 8 && $c >= $n - 9) || ($r >= $n - 9 && $c <= 8)) {
                    continue;
                }
                self::drawAlignment($grid, $reserved, $c, $r);
            }
        }

        for ($i = 8; $i < $n - 8; $i++) {
            $bit = $i % 2 === 0 ? 1 : 0;
            $grid[6][$i] = $bit;
            $grid[$i][6] = $bit;
            $reserved[6][$i] = 1;
            $reserved[$i][6] = 1;
        }

        $reserved[8][8] = 1;
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$i] = 1;
            $reserved[$i][8] = 1;
            $reserved[8][$n - 1 - $i] = 1;
            $reserved[$n - 1 - $i][8] = 1;
        }
        $reserved[8][7] = 1;
        $reserved[7][8] = 1;

        if ($version >= 7) {
            for ($r = 0; $r < 6; $r++) {
                for ($c = 0; $c < 3; $c++) {
                    $reserved[$r][$n - 11 + $c] = 1;
                    $reserved[$n - 11 + $c][$r] = 1;
                }
            }
        }

        $grid[$n - 8][8] = 1;
        $reserved[$n - 8][8] = 1;
    }

    private static function placeData(array &$grid, array $reserved, array $bits)
    {
        $n = count($grid);
        $k = 0;
        $up = true;
        $bitCount = count($bits);
        for ($col = $n - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            for ($i = 0; $i < $n; $i++) {
                $row = $up ? $n - 1 - $i : $i;
                for ($s = 0; $s < 2; $s++) {
                    $x = $col - $s;
                    if ($reserved[$row][$x]) {
                        continue;
                    }
                    $grid[$row][$x] = $k < $bitCount ? $bits[$k++] : 0;
                }
            }
            $up = !$up;
        }
    }

    private static function cloneGrid(array $grid)
    {
        $out = [];
        foreach ($grid as $row) {
            $out[] = $row;
        }
        return $out;
    }

    private static function maskBit($mask, $r, $c)
    {
        switch ($mask) {
            case 0: return ($r + $c) % 2 === 0;
            case 1: return $r % 2 === 0;
            case 2: return $c % 3 === 0;
            case 3: return ($r + $c) % 3 === 0;
            case 4: return (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0;
            case 5: return ($r * $c) % 2 + ($r * $c) % 3 === 0;
            case 6: return (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0;
            default: return (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0;
        }
    }

    private static function applyMask(array &$grid, array $reserved, $mask)
    {
        $n = count($grid);
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if (!$reserved[$r][$c] && self::maskBit($mask, $r, $c)) {
                    $grid[$r][$c] ^= 1;
                }
            }
        }
    }

    private static function drawFormat(array &$grid, $level, $mask)
    {
        $n = count($grid);
        $data = (self::LEVEL_BITS[$level] << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (intdiv($rem, 512) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = function ($i) use ($bits) {
            return ($bits >> $i) & 1;
        };

        for ($i = 0; $i <= 5; $i++) {
            $grid[$i][8] = $bit($i);
        }
        $grid[7][8] = $bit(6);
        $grid[8][8] = $bit(7);
        $grid[8][7] = $bit(8);
        for ($i = 9; $i < 15; $i++) {
            $grid[8][14 - $i] = $bit($i);
        }
        for ($i = 0; $i < 8; $i++) {
            $grid[8][$n - 1 - $i] = $bit($i);
        }
        for ($i = 8; $i < 15; $i++) {
            $grid[$n - 15 + $i][8] = $bit($i);
        }
        $grid[$n - 8][8] = 1;
    }

    private static function drawVersion(array &$grid, $version)
    {
        if ($version < 7) {
            return;
        }
        $n = count($grid);
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (intdiv($rem, 2048) * 0x1f25);
        }
        $bits = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = ($bits >> $i) & 1;
            $a = $n - 11 + ($i % 3);
            $b = intdiv($i, 3);
            $grid[$b][$a] = $bit;
            $grid[$a][$b] = $bit;
        }
    }

    private static function penalty(array $grid)
    {
        $n = count($grid);
        $score = 0;

        $runPenalty = function ($get) use ($n, &$score) {
            for ($i = 0; $i < $n; $i++) {
                $run = 1;
                for ($j = 1; $j <= $n; $j++) {
                    if ($j < $n && $get($i, $j) === $get($i, $j - 1)) {
                        $run++;
                    } else {
                        if ($run >= 5) {
                            $score += $run - 2;
                        }
                        $run = 1;
                    }
                }
            }
        };
        $runPenalty(function ($i, $j) use ($grid) { return $grid[$i][$j]; });
        $runPenalty(function ($i, $j) use ($grid) { return $grid[$j][$i]; });

        for ($r = 0; $r < $n - 1; $r++) {
            for ($c = 0; $c < $n - 1; $c++) {
                $v = $grid[$r][$c];
                if ($v === $grid[$r][$c + 1] && $v === $grid[$r + 1][$c] && $v === $grid[$r + 1][$c + 1]) {
                    $score += 3;
                }
            }
        }

        $finderLike = function ($get) use ($n, &$score) {
            for ($i = 0; $i < $n; $i++) {
                $s = '';
                for ($j = 0; $j < $n; $j++) {
                    $s .= $get($i, $j);
                }
                for ($k = 0; $k <= $n - 11; $k++) {
                    $chunk = substr($s, $k, 11);
                    if ($chunk === '10111010000' || $chunk === '00001011101') {
                        $score += 40;
                    }
                }
            }
        };
        $finderLike(function ($i, $j) use ($grid) { return $grid[$i][$j]; });
        $finderLike(function ($i, $j) use ($grid) { return $grid[$j][$i]; });

        $dark = 0;
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($grid[$r][$c]) {
                    $dark++;
                }
            }
        }
        $total = $n * $n;
        $score += 10 * (int) floor(abs($dark * 100 / $total - 50) / 5);
        return $score;
    }
}
