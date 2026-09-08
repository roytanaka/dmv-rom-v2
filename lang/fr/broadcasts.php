<?php

// Chaînes du châssis du compositeur (spec #479, ADR-0024 §4, §6) — le peu que l'application
// ajoute autour d'un message qui est par ailleurs du contenu. Le corps d'un message part tel
// qu'il a été rédigé, dans une seule langue; seul le pied de page « personnes non jointes » de
// la copie de l'expéditeur est du châssis, rendu dans la langue de l'expéditeur. Reflète
// lang/en/broadcasts.php clé pour clé.
return [
    // La copie de l'expéditeur, le signal de fin (ADR-0024 §4). Son pied de page nomme les
    // destinataires non joints : :names est la liste, jusqu'à vingt, avec `and_more` ajouté
    // pour le surplus.
    'sender_copy' => [
        'undelivered' => 'Impossible de livrer à : :names',
        'and_more' => 'et :count de plus',
    ],
];
