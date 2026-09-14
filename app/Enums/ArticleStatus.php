<?php

namespace App\Enums;

/**
 * A Help article's publication status (ADR-0025). A `published` article appears in
 * the index and the contextual "?" link; a `draft` is hidden from both but still
 * renders at its URL, so review happens on staging with real chrome. New articles
 * default to published; the two Getting started articles stay draft until their
 * screenshots land.
 *
 * Backed string enum: the stored value is the case's slug, kept off the reader-facing
 * page and shown only on the super-tier ledger.
 */
enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
