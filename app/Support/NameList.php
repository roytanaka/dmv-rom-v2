<?php

namespace App\Support;

/**
 * Join names for a sentence in the active locale: "A", "A and B", "A, B and C". Used by the
 * DMV Settings refusals that name the roles or stewardships in the way.
 */
final class NameList
{
    /**
     * @param  iterable<string>  $names
     */
    public static function join(iterable $names): string
    {
        return collect($names)->join(', ', ' '.__('dmv_settings.and').' ');
    }
}
