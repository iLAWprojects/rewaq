<?php

namespace App\Services;

class QrCodeService
{
    /**
     * Standard Galois Field GF(2^8) with primitive polynomial 0x11d
     */
    protected static ?array $gfExp = null;

    protected static ?array $gfLog = null;

    /**
     * Initialize Galois Field tables.
     */
    protected static function initGf(): void
    {
        if (self::$gfExp !== null) {
            return;
        }

        self::$gfExp = array_fill(0, 512, 0);
        self::$gfLog = array_fill(0, 256, 0);

        $val = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$gfExp[$i] = $val;
            self::$gfLog[$val] = $i;
            $val <<= 1;
            if ($val & 0x100) {
                $val ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$gfExp[$i] = self::$gfExp[$i - 255];
        }
    }

    protected static function gfMul(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) {
            return 0;
        }
        self::initGf();

        return self::$gfExp[self::$gfLog[$x] + self::$gfLog[$y]];
    }

    /**
     * Generate Reed-Solomon error correction generator polynomial.
     */
    protected static function rsGeneratorPoly(int $ecCount): array
    {
        $poly = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $factor = [1, self::$gfExp[$i]];
            $newPoly = array_fill(0, count($poly) + 1, 0);
            for ($j = 0; $j < count($poly); $j++) {
                $newPoly[$j] ^= self::gfMul($poly[$j], $factor[0]);
                $newPoly[$j + 1] ^= self::gfMul($poly[$j], $factor[1]);
            }
            $poly = $newPoly;
        }

        return $poly;
    }

    /**
     * Calculate Reed-Solomon error correction codewords.
     */
    protected static function calculateRs(array $data, int $ecCount): array
    {
        self::initGf();
        $gen = self::rsGeneratorPoly($ecCount);
        $remainder = array_merge($data, array_fill(0, $ecCount, 0));

        for ($i = 0; $i < count($data); $i++) {
            $coef = $remainder[$i];
            if ($coef !== 0) {
                for ($j = 0; $j < count($gen); $j++) {
                    $remainder[$i + $j] ^= self::gfMul($gen[$j], $coef);
                }
            }
        }

        return array_slice($remainder, count($data));
    }

    /**
     * Version specifications (Version, Data Codewords, EC Codewords, Blocks) for Level L (7% error recovery)
     */
    protected static array $versionSpecs = [
        1 => ['version' => 1, 'size' => 21, 'total' => 26,  'data' => 19,  'ec' => 7,  'blocks' => 1, 'align' => []],
        2 => ['version' => 2, 'size' => 25, 'total' => 44,  'data' => 34,  'ec' => 10, 'blocks' => 1, 'align' => [6, 18]],
        3 => ['version' => 3, 'size' => 29, 'total' => 70,  'data' => 55,  'ec' => 15, 'blocks' => 1, 'align' => [6, 22]],
        4 => ['version' => 4, 'size' => 33, 'total' => 100, 'data' => 80,  'ec' => 20, 'blocks' => 1, 'align' => [6, 26]],
        5 => ['version' => 5, 'size' => 37, 'total' => 134, 'data' => 108, 'ec' => 26, 'blocks' => 1, 'align' => [6, 30]],
        6 => ['version' => 6, 'size' => 41, 'total' => 172, 'data' => 136, 'ec' => 18, 'blocks' => 2, 'align' => [6, 34]],
        7 => ['version' => 7, 'size' => 45, 'total' => 196, 'data' => 156, 'ec' => 20, 'blocks' => 2, 'align' => [6, 22, 38]],
    ];

    /**
     * Generate 2D Matrix of QR modules (true = dark, false = light).
     */
    public static function generateMatrix(string $text): array
    {
        $bytes = array_values(unpack('C*', $text));
        $dataLen = count($bytes);

        // Find appropriate version
        $spec = null;
        foreach (self::$versionSpecs as $ver => $vData) {
            // Byte mode overhead: 4 bits mode + 8 bits length = 12 bits = 1.5 bytes.
            // Available capacity for text = data codewords - 2
            if ($dataLen <= ($vData['data'] - 2)) {
                $spec = $vData;
                break;
            }
        }

        if (! $spec) {
            $spec = self::$versionSpecs[7];
            $bytes = array_slice($bytes, 0, $spec['data'] - 3);
            $dataLen = count($bytes);
        }

        $size = $spec['size'];
        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        $isFunction = array_fill(0, $size, array_fill(0, $size, false));

        // 1. Finder patterns
        self::placeFinderPattern($matrix, $isFunction, 0, 0);
        self::placeFinderPattern($matrix, $isFunction, $size - 7, 0);
        self::placeFinderPattern($matrix, $isFunction, 0, $size - 7);

        // 2. Alignment patterns
        if (! empty($spec['align'])) {
            $coords = $spec['align'];
            foreach ($coords as $r) {
                foreach ($coords as $c) {
                    if ($matrix[$r][$c] !== null) {
                        continue;
                    }
                    self::placeAlignmentPattern($matrix, $isFunction, $r - 2, $c - 2);
                }
            }
        }

        // 3. Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            if ($matrix[6][$i] === null) {
                $matrix[6][$i] = ($i % 2 === 0);
                $isFunction[6][$i] = true;
            }
            if ($matrix[$i][6] === null) {
                $matrix[$i][6] = ($i % 2 === 0);
                $isFunction[$i][6] = true;
            }
        }

        // 4. Dark module
        $matrix[4 * $spec['version'] + 9][8] = true;
        $isFunction[4 * $spec['version'] + 9][8] = true;

        // Reserve format info area
        for ($i = 0; $i < 9; $i++) {
            $isFunction[8][$i] = true;
            $isFunction[$i][8] = true;
            if ($i < 8) {
                $isFunction[8][$size - 1 - $i] = true;
                $isFunction[$size - 1 - $i][8] = true;
            }
        }

        // 5. Construct bitstream (Byte Mode: 0100)
        $bits = [];
        // Mode 0100
        $bits = array_merge($bits, [0, 1, 0, 0]);
        // Length (8 bits for V1-V9)
        for ($b = 7; $b >= 0; $b--) {
            $bits[] = ($dataLen >> $b) & 1;
        }
        // Data bytes
        foreach ($bytes as $byte) {
            for ($b = 7; $b >= 0; $b--) {
                $bits[] = ($byte >> $b) & 1;
            }
        }
        // Terminator (up to 4 zeroes)
        $maxDataBits = $spec['data'] * 8;
        $termLen = min(4, $maxDataBits - count($bits));
        for ($i = 0; $i < $termLen; $i++) {
            $bits[] = 0;
        }
        // Pad to byte
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        // Pad bytes (0xEC, 0x11)
        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($bits) < $maxDataBits) {
            $pb = $padBytes[$padIdx % 2];
            for ($b = 7; $b >= 0; $b--) {
                $bits[] = ($pb >> $b) & 1;
            }
            $padIdx++;
        }

        // Convert data bits to data codewords
        $dataCodewords = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $val = 0;
            for ($b = 0; $b < 8; $b++) {
                $val = ($val << 1) | $bits[$i + $b];
            }
            $dataCodewords[] = $val;
        }

        // Divide into blocks and calculate EC
        $numBlocks = $spec['blocks'];
        $dataPerBlock = intdiv($spec['data'], $numBlocks);
        $ecPerBlock = $spec['ec'];

        $blocksData = [];
        $blocksEc = [];
        for ($blk = 0; $blk < $numBlocks; $blk++) {
            $blkData = array_slice($dataCodewords, $blk * $dataPerBlock, $dataPerBlock);
            $blkEc = self::calculateRs($blkData, $ecPerBlock);
            $blocksData[] = $blkData;
            $blocksEc[] = $blkEc;
        }

        // Interleave codewords
        $finalCodewords = [];
        for ($i = 0; $i < $dataPerBlock; $i++) {
            for ($blk = 0; $blk < $numBlocks; $blk++) {
                if (isset($blocksData[$blk][$i])) {
                    $finalCodewords[] = $blocksData[$blk][$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            for ($blk = 0; $blk < $numBlocks; $blk++) {
                if (isset($blocksEc[$blk][$i])) {
                    $finalCodewords[] = $blocksEc[$blk][$i];
                }
            }
        }

        // Convert final codewords to bitstream
        $finalBits = [];
        foreach ($finalCodewords as $cw) {
            for ($b = 7; $b >= 0; $b--) {
                $finalBits[] = ($cw >> $b) & 1;
            }
        }

        // 6. Place data bits in matrix (Right to Left in 2-column ribbons, zigzagging)
        $bitIdx = 0;
        $totalBits = count($finalBits);
        $up = true;

        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--; // Skip vertical timing column
            }
            $rowRange = $up ? range($size - 1, 0, -1) : range(0, $size - 1, 1);
            foreach ($rowRange as $row) {
                foreach ([$col, $col - 1] as $c) {
                    if (! $isFunction[$row][$c]) {
                        $bit = ($bitIdx < $totalBits) ? $finalBits[$bitIdx++] : 0;
                        // Apply Mask Pattern 0: (row + col) % 2 == 0
                        $mask = (($row + $c) % 2 === 0);
                        $matrix[$row][$c] = ($bit === 1) ^ $mask;
                    }
                }
            }
            $up = ! $up;
        }

        // 7. Write Format Information (Error Level L = 01, Mask 0 = 000 -> 01000 = 8)
        // Format string with BCH (15, 5) code for (Level L, Mask 0) XORed with 0x5412 = 0x77C4 (111011111000100)
        $formatBits = [1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 0, 0, 1, 0, 0];

        // Write to top-left
        $matrix[8][0] = (bool) $formatBits[0];
        $matrix[8][1] = (bool) $formatBits[1];
        $matrix[8][2] = (bool) $formatBits[2];
        $matrix[8][3] = (bool) $formatBits[3];
        $matrix[8][4] = (bool) $formatBits[4];
        $matrix[8][5] = (bool) $formatBits[5];
        $matrix[8][7] = (bool) $formatBits[6];
        $matrix[8][8] = (bool) $formatBits[7];
        $matrix[7][8] = (bool) $formatBits[8];
        $matrix[5][8] = (bool) $formatBits[9];
        $matrix[4][8] = (bool) $formatBits[10];
        $matrix[3][8] = (bool) $formatBits[11];
        $matrix[2][8] = (bool) $formatBits[12];
        $matrix[1][8] = (bool) $formatBits[13];
        $matrix[0][8] = (bool) $formatBits[14];

        // Write to bottom and right
        for ($i = 0; $i < 7; $i++) {
            $matrix[$size - 1 - $i][8] = (bool) $formatBits[$i];
        }
        for ($i = 0; $i < 8; $i++) {
            $matrix[8][$size - 8 + $i] = (bool) $formatBits[7 + $i];
        }

        return $matrix;
    }

    protected static function placeFinderPattern(array &$matrix, array &$isFunction, int $startR, int $startC): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $currR = $startR + $r;
                $currC = $startC + $c;
                if ($currR < 0 || $currR >= count($matrix) || $currC < 0 || $currC >= count($matrix)) {
                    continue;
                }
                $isFunction[$currR][$currC] = true;
                if ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) {
                    if ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) {
                        $matrix[$currR][$currC] = true;
                    } else {
                        $matrix[$currR][$currC] = false;
                    }
                } else {
                    $matrix[$currR][$currC] = false; // Separator
                }
            }
        }
    }

    protected static function placeAlignmentPattern(array &$matrix, array &$isFunction, int $startR, int $startC): void
    {
        for ($r = 0; $r < 5; $r++) {
            for ($c = 0; $c < 5; $c++) {
                $currR = $startR + $r;
                $currC = $startC + $c;
                $isFunction[$currR][$currC] = true;
                if ($r === 0 || $r === 4 || $c === 0 || $c === 4 || ($r === 2 && $c === 2)) {
                    $matrix[$currR][$currC] = true;
                } else {
                    $matrix[$currR][$currC] = false;
                }
            }
        }
    }

    /**
     * Generate an SVG string representation of the QR Code.
     *
     * @param  string  $text  The exact URL or content to encode
     * @param  int  $width  Output SVG width
     * @param  int  $height  Output SVG height
     * @param  string  $fgColor  Foreground color (Riwaq forest green: #173D36, or custom)
     * @param  string  $bgColor  Background color (#FFFDF8, or 'none')
     * @param  int  $margin  Quiet zone in modules
     * @return string Valid SVG markup
     */
    public static function generateSvg(
        string $text,
        int $width = 200,
        int $height = 200,
        string $fgColor = '#173D36',
        string $bgColor = '#FFFDF8',
        int $margin = 4
    ): string {
        $matrix = self::generateMatrix($text);
        $size = count($matrix);
        $totalSize = $size + ($margin * 2);

        $rects = [];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (! empty($matrix[$r][$c])) {
                    $x = $c + $margin;
                    $y = $r + $margin;
                    $rects[] = "<rect x=\"{$x}\" y=\"{$y}\" width=\"1\" height=\"1\" fill=\"{$fgColor}\" />";
                }
            }
        }

        $bgRect = ($bgColor !== 'none' && ! empty($bgColor))
            ? "<rect width=\"{$totalSize}\" height=\"{$totalSize}\" fill=\"{$bgColor}\" rx=\"4\" />"
            : '';

        $rectsStr = implode("\n        ", $rects);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$totalSize} {$totalSize}" width="{$width}" height="{$height}" role="img" aria-label="QR Code">
    <title>QR Code</title>
    {$bgRect}
    <g class="qr-modules">
        {$rectsStr}
    </g>
</svg>
SVG;
    }

    /**
     * Generate data URI for direct <img> src usage.
     */
    public static function generateDataUri(string $text, int $size = 200): string
    {
        $svg = self::generateSvg($text, $size, $size);

        return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
    }
}
