<?php
/**
 * This file is part of Icinga Web 2.
 *
 * Icinga Web 2 - Head for multiple monitoring backends.
 * Copyright (C) 2013 Icinga Development Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @copyright  2013 Icinga Development Team <info@icinga.org>
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author     Icinga Development Team <info@icinga.org>
 *
 */
namespace Icinga\Util;

/**
 * Provide functions to change and convert colors.
 */
class Color {
    /**
     * Convert a given color string to an rgb-array containing
     * each color as a decimal value.
     *
     * @param $color    The color-string #RRGGBB
     *
     * @return array    The converted rgb-array.
     */
    public static function rgbAsArray($color)
    {
        if (substr($color, 0, 1) !== '#') {
            $color = '#' . $color;
        }
        if (strlen($color) !== 7) {
            return;
        }
        $r = (float)intval(substr($color, 1, 2), 16);
        $g = (float)intval(substr($color, 3, 2), 16);
        $b = (float)intval(substr($color, 5, 2), 16);
        return array($r, $g, $b);
    }

    /**
     * Convert a rgb array to a color-string
     *
     * @param array $rgb    The rgb-array
     *
     * @return string   The color string #RRGGBB
     */
    public static function arrayToRgb(array $rgb)
    {
        $r = (string)dechex($rgb[0]);
        $g = (string)dechex($rgb[1]);
        $b = (string)dechex($rgb[2]);
        return '#'
            . (strlen($r) > 1 ? $r : '0' . $r)
            . (strlen($g) > 1 ? $g : '0' . $g)
            . (strlen($b) > 1 ? $b : '0' . $b);
    }

    /**
     * Change the saturation for a given color.
     *
     * @param $color    string  The color to change
     * @param $change   float   The change.
     *                   0.0 creates a black-and-white image.
     *                   0.5 reduces the color saturation by half.
     *                   1.0 causes no change.
     *                   2.0 doubles the color saturation.
     * @return string
     */
    public static function changeSaturation($color, $change)
    {
        return self::arrayToRgb(self::changeRgbSaturation(self::rgbAsArray($color), $change));
    }

    /**
     * Change the brightness for a given color
     *
     * @param $color    string  The color to change
     * @param $change   float   The change in percent
     *
     * @return string
     */
    public static function changeBrightness($color, $change)
    {
        return self::arrayToRgb(self::changeRgbBrightness(self::rgbAsArray($color), $change));
    }

    /**
     * @param $rgb      array   The rgb-array to change
     * @param $change   float   The factor
     *
     * @return array    The updated rgb-array
     */
    private static function changeRgbSaturation(array $rgb, $change)
    {
        $pr = 0.499; // 0.299
        $pg = 0.387; // 0.587
        $pb = 0.114; // 0.114
        $r = $rgb[0];
        $g = $rgb[1];
        $b = $rgb[2];
        $p = sqrt(
            $r * $r * $pr +
            $g * $g * $pg +
            $b * $b * $pb
        );
        $rgb[0] = (int)($p + ($r - $p) * $change);
        $rgb[1] = (int)($p + ($g - $p) * $change);
        $rgb[2] = (int)($p + ($b - $p) * $change);
        return $rgb;
    }

    /**
     * @param $rgb      array   The rgb-array to change
     * @param $change   float   The factor
     *
     * @return array    The updated rgb-array
     */
    private static function changeRgbBrightness(array $rgb, $change)
    {
        $red = $rgb[0] + ($rgb[0] * $change);
        $green = $rgb[1] + ($rgb[1] * $change);
        $blue = $rgb[2] + ($rgb[2] * $change);
        $rgb[0] = $red < 255 ? (int) $red : 255;
        $rgb[1] = $green < 255 ? (int) $green : 255;
        $rgb[2] = $blue < 255 ? (int) $blue : 255;
        return $rgb;
    }
}
