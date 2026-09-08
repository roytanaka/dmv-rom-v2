<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Machine-translation exclusions
    |--------------------------------------------------------------------------
    |
    | Lang files (by filename, without extension — i.e. the Laravel namespace)
    | that the machine-translation baseline step MUST skip. French chrome is
    | machine-translated as a baseline (ADR-0004), but these files are
    | hand-authored with ROM's official, legally and relationally weighty
    | wording. An automated pass over lang/fr/* must never overwrite them, so
    | the MT step consults this list and skips any matching file by name. This
    | is a structural wall, not a per-string annotation.
    |
    */

    'machine_translation_excludes' => [
        'institutional',
    ],

];
