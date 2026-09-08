<?php

namespace App\Enums;

/**
 * The surface a composer's Audience menu is opened from (ADR-0024 §6). Every
 * Broadcast/Direct-message Audience is resolved *for a context*: the same
 * Audience key means different people in different places, and which Audiences
 * an actor may pick at all depends on where they are. The five contexts mirror
 * the six compose entry points (the roster tab shares the Group context).
 *
 * Backed string enum: the stored value is the request's `context` parameter.
 */
enum ContextType: string
{
    // A Group page (and its roster tab): the Group's own roster and officer
    // Audiences, resolved from that Group.
    case Group = 'group';

    // An opened Schedule: the Sign-ups-on-this-Schedule Audience, plus the
    // owning Group's Audiences.
    case Schedule = 'schedule';

    // A single Shift: the Sign-ups-on-this-Shift Audience alone.
    case Shift = 'shift';

    // The org-wide Directory: the org-wide Broadcast Audiences and the three
    // leadership Audiences — never scoped to any one Group.
    case Directory = 'directory';

    // A Member's profile: the one-Member Direct-message Audience.
    case Member = 'member';
}
