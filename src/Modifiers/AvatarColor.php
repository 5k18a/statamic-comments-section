<?php

namespace Skalisty\Comments\Modifiers;

use Statamic\Modifiers\Modifier;

class AvatarColor extends Modifier
{
    /**
     * Deterministyczny kolor tła awatara z nazwy autora (ten sam autor => ten sam kolor).
     * Zwraca HSL o stałej saturacji/jasności => dobry kontrast z białym inicjałem.
     */
    public function index($value, $params, $context): string
    {
        $seed = trim((string) $value);

        if ($seed === '') {
            $seed = 'anonymous';
        }

        $hue = crc32(mb_strtolower($seed)) % 360;

        return "hsl({$hue}, 55%, 45%)";
    }
}
