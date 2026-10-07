<?php

namespace App\Enums;

/**
 * Who reads a Document library Folder (ADR-0030 §5). Stored on top-level Folders only;
 * subfolders and Documents inherit it, and the library root is `Group`.
 */
enum DocumentVisibility: string
{
    /** Members of the owning Group. */
    case Group = 'group';

    /** Every signed-in Member. */
    case Members = 'members';
}
