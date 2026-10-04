<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

/*
 * The Post-shift report's counted copy (#668, PRD #651): a count of one reads in the singular,
 * and "N of M recorded" agrees with N in French, where 0 and 1 take the singular. The front end
 * reads the same strings through laravel-vue-i18n's `transChoice`, which follows Laravel's plural
 * syntax and rules, so the translator here is the reference.
 */
function postShiftTranslator(string $locale): Translator
{
    return new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 2).'/lang'), $locale);
}

it('reads a saved count in the singular for one and the plural otherwise', function (string $locale, int $count, string $expected) {
    expect(postShiftTranslator($locale)->choice('group.scheduling_panel.agenda.sign_out.recorded', $count, ['count' => $count]))
        ->toBe($expected);
})->with([
    ['en', 0, '0 visitors'],
    ['en', 1, '1 visitor'],
    ['en', 2, '2 visitors'],
    ['fr', 0, '0 visiteur·euse'],
    ['fr', 1, '1 visiteur·euse'],
    ['fr', 2, '2 visiteur·euses'],
]);

it('agrees "N of M recorded" with N', function (string $locale, int $recorded, string $expected) {
    expect(postShiftTranslator($locale)->choice('group.scheduling_panel.agenda.sign_out.progress', $recorded, ['recorded' => $recorded, 'total' => 4]))
        ->toBe($expected);
})->with([
    ['en', 1, '1 of 4 recorded'],
    ['en', 3, '3 of 4 recorded'],
    ['fr', 0, '0 sur 4 enregistré'],
    ['fr', 1, '1 sur 4 enregistré'],
    ['fr', 3, '3 sur 4 enregistrés'],
]);
