<?php

namespace App\Enums;

/**
 * The kind of a meeting's attached link (#190, PRD #186) — the conventional set
 * of labelled external URLs a Group hangs off a meeting. The label is chrome
 * (translated from lang/{en,fr}/group.php); the URL itself is content. When the
 * Documents capability lands, these upgrade to access-controlled documents.
 *
 * Backed string enum: the stored value is the snake_case case value.
 */
enum MeetingLinkKind: string
{
    case Agenda = 'agenda';
    case Minutes = 'minutes';
    case Report = 'report';
}
