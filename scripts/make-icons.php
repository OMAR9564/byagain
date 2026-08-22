<?php

declare(strict_types=1);

/*
|----------------------------------------------------------------------------
| Icon generator
|----------------------------------------------------------------------------
|
| Rasterises the mark in public/icons/icon.svg — that file is the source of
| truth; this one mirrors its geometry because there is no SVG rasteriser in
| the toolchain.
|
|   php scripts/make-icons.php
|
| A build tool, not part of the application. It lives outside app/ on purpose.
|
| Everything is drawn at four times the target and resampled down, because
| GD's shape primitives have no anti-aliasing and a 512px circle drawn
| directly comes out visibly stepped.
|
*/

const CANVAS = 512;
const SUPERSAMPLE = 4;

const ACCENT = [0x8A, 0x5A, 0x2B];
const CREAM = [0xFB, 0xFA, 0xF8];

/** Bars as [x, y, width, height, opacity], on the 512 grid of icon.svg. */
const BARS = [
    [112, 124, 240, 56, 0.45],
    [112, 224, 288, 64, 1.0],
    [112, 332, 180, 56, 0.45],
];

/**
 * Below roughly 48px the three bars collapse into a smudge, so the small
 * sizes get a simplified two-bar cut with thicker strokes. Optical work, not
 * laziness: a mark that is unreadable at 16px is not a favicon.
 */
const BARS_SMALL = [
    [96, 168, 256, 88, 0.45],
    [96, 296, 320, 96, 1.0],
];

$targets = [
    // path                             size  scale  maskable
    'public/icons/icon-192.png' => [192, 1.0, false],
    'public/icons/icon-512.png' => [512, 1.0, false],
    'public/icons/icon-maskable-512.png' => [512, 0.85, true],
    'public/icons/apple-touch-icon.png' => [180, 1.0, false],
    'public/icons/favicon-32.png' => [32, 1.0, false],
    'public/icons/favicon-16.png' => [16, 1.0, false],
];

$root = dirname(__DIR__);

foreach ($targets as $path => [$size, $scale, $maskable]) {
    $image = render($size, $scale, $maskable);

    $full = $root.'/'.$path;

    if (! is_dir(dirname($full))) {
        mkdir(dirname($full), 0755, true);
    }

    imagepng($image, $full, 9);
    imagedestroy($image);

    printf("%-42s %4dpx  %s\n", $path, $size, number_format((int) filesize($full)).' bytes');
}

echo "\nDone. icon.svg remains the source of truth — redraw there first.\n";

/**
 * @return GdImage
 */
function render(int $size, float $scale, bool $maskable)
{
    $big = $size * SUPERSAMPLE;

    $canvas = imagecreatetruecolor($big, $big);
    imagealphablending($canvas, true);

    $accent = imagecolorallocate($canvas, ...ACCENT);
    imagefilledrectangle($canvas, 0, 0, $big, $big, $accent);

    // Maskable icons are cropped to whatever shape the platform likes, so the
    // artwork shrinks into the safe zone while the background still bleeds to
    // the edge.
    $inset = $maskable ? $scale : $scale;

    // Small sizes use the simplified cut.
    $bars = $size <= 48 ? BARS_SMALL : BARS;

    $cream = imagecolorallocate($canvas, ...CREAM);

    foreach ($bars as [$x, $y, $w, $h, $opacity]) {
        // 512-grid coordinates → supersampled pixels, with the optional
        // shrink applied about the centre.
        $unit = $big / CANVAS;

        $cx = CANVAS / 2;
        $sx = ($cx + ($x - $cx) * $inset) * $unit;
        $sy = ($cx + ($y - $cx) * $inset) * $unit;
        $sw = $w * $inset * $unit;
        $sh = $h * $inset * $unit;

        // Drawn solid onto a copy of the canvas and then blended in as a
        // whole, rather than drawn in a semi-transparent colour.
        //
        // A pill is a rectangle between two circles, so the caps overlap the
        // body. Painting that shape directly in a translucent colour paints
        // the overlap twice and leaves visibly darker blobs at both ends.
        $layer = imagecreatetruecolor($big, $big);
        imagecopy($layer, $canvas, 0, 0, 0, 0, $big, $big);

        pill($layer, (int) $sx, (int) $sy, (int) $sw, (int) $sh, $cream);

        imagecopymerge($canvas, $layer, 0, 0, 0, 0, $big, $big, (int) round($opacity * 100));
        imagedestroy($layer);
    }

    $out = imagecreatetruecolor($size, $size);
    imagecopyresampled($out, $canvas, 0, 0, 0, 0, $size, $size, $big, $big);
    imagedestroy($canvas);

    return $out;
}

/**
 * A rounded bar: one rectangle between two circles.
 *
 * @param  GdImage  $image
 */
function pill($image, int $x, int $y, int $w, int $h, int $colour): void
{
    $r = intdiv($h, 2);

    imagefilledrectangle($image, $x + $r, $y, $x + $w - $r, $y + $h, $colour);
    imagefilledellipse($image, $x + $r, $y + $r, $h, $h, $colour);
    imagefilledellipse($image, $x + $w - $r, $y + $r, $h, $h, $colour);
}
