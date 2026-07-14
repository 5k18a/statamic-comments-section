<?php

namespace Skalisty\Comments\Modifiers;

use Statamic\Modifiers\Modifier;

class AvatarInitial extends Modifier
{
    /**
     * Pierwsza litera nazwy autora (wielka). Fallback '?' dla pustej wartości.
     */
    public function index($value, $params, $context): string
    {
        $name = trim((string) $value);

        if ($name === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }
}
