<?php

namespace App\Enums;

/**
 * The review state of a Help article's French copy (ADR-0025, amending ADR-0004).
 * French articles ship machine-translated in the same pull request as the English
 * one and stay `machine_translated` until a human reads them, then `reviewed`. The
 * state is invisible to readers — an unverified French string ships as chrome
 * (ADR-0004) — and is shown only on the super-tier ledger.
 *
 * Backed string enum: the stored value is the case's slug.
 */
enum FrenchState: string
{
    case MachineTranslated = 'machine_translated';
    case Reviewed = 'reviewed';
}
