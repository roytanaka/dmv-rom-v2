<?php

namespace App\Enums;

/**
 * What a Document holds (ADR-0030 §7): an uploaded file on the private disk, or a link to
 * a web address. Both pass the same access check and log on the download route.
 */
enum DocumentKind: string
{
    case File = 'file';
    case Link = 'link';
}
