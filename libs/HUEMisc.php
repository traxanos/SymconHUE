<?php

class HUEMisc
{

    public static function HEX2HSV($h)
    {
        $r = substr($h, 0, 2);
        $g = substr($h, 2, 2);
        $b = substr($h, 4, 2);
        return HUEMisc::RGB2HSV(hexdec($r), hexdec($g), hexdec($b));
    }
    public static function HSV2HEX($h, $s, $v)
    {
        $rgb = HUEMisc::HSV2RGB($h, $s, $v);
        $r = str_pad(dechex($rgb['r']), 2, 0, STR_PAD_LEFT);
        $g = str_pad(dechex($rgb['g']), 2, 0, STR_PAD_LEFT);
        $b = str_pad(dechex($rgb['b']), 2, 0, STR_PAD_LEFT);
        return $r.$g.$b;
    }
    public static function RGB2HSV($r, $g, $b)
    {
        if (!($r >= 0 && $r <= 255)) {
            throw new Exception("h property must be between 0 and 255, but is: ${r}");
        }
        if (!($g >= 0 && $g <= 255)) {
            throw new Exception("s property must be between 0 and 255, but is: ${g}");
        }
        if (!($b >= 0 && $b <= 255)) {
            throw new Exception("v property must be between 0 and 255, but is: ${b}");
        }
        $r = ($r / 255);
        $g = ($g / 255);
        $b = ($b / 255);
        $maxRGB = max($r, $g, $b);
        $minRGB = min($r, $g, $b);
        $chroma = $maxRGB - $minRGB;
        $v = $maxRGB * 254;
        if ($chroma == 0) {
            return array('h' => 0, 's' => 0, 'v' => round($v));
        }
        $s = ($chroma / $maxRGB) * 254;
        if ($r == $minRGB) {
            $h = 3 - (($g - $b) / $chroma);
        } elseif ($b == $minRGB) {
            $h = 1 - (($r - $g) / $chroma);
        } else {// $g == $minRGB
            $h = 5 - (($b - $r) / $chroma);
        }
        $h = $h / 6 * 65535;
        return array('h' => round($h), 's' => round($s), 'v' => round($v));
    }
    public static function HSV2RGB($h, $s, $v)
    {
        if (!($h >= 0 && $h <= (21845*3))) {
            throw new Exception("h property must be between 0 and 65535, but is: ${h}");
        }
        if (!($s >= 0 && $s <= 254)) {
            throw new Exception("s property must be between 0 and 254, but is: ${s}");
        }
        if (!($v >= 0 && $v <= 254)) {
            throw new Exception("v property must be between 0 and 254, but is: ${v}");
        }
        $h = $h * 6 / (21845*3);
        $s = $s / 254;
        $v = $v / 254;
        $i = floor($h);
        $f = $h - $i;
        $m = $v * (1 - $s);
        $n = $v * (1 - $s * $f);
        $k = $v * (1 - $s * (1 - $f));
        switch ($i) {
        case 0:
            list($r, $g, $b) = array($v, $k, $m);
            break;
        case 1:
            list($r, $g, $b) = array($n, $v, $m);
            break;
        case 2:
            list($r, $g, $b) = array($m, $v, $k);
            break;
        case 3:
            list($r, $g, $b) = array($m, $n, $v);
            break;
        case 4:
            list($r, $g, $b) = array($k, $m, $v);
            break;
        case 5:
        case 6:
            list($r, $g, $b) = array($v, $m, $n);
            break;
        }
        $r = round($r * 255);
        $g = round($g * 255);
        $b = round($b * 255);
        return array('r' => $r, 'g' => $g, 'b' => $b);
    }
}
