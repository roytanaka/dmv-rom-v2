<?php

namespace App\Support\Audiences;

use App\Enums\AudienceKey;

/**
 * One Audience on offer in a context (ADR-0024 §5–6): its {@see AudienceKey}, the
 * parameter that pins a parameterised key (a status / category value, a child
 * Group slug, or a Member id), and the label the menu shows and the record stores.
 * A plain descriptor — the resolver produces these for the index; resolving one to
 * its recipients is {@see ResolvedAudience}.
 */
final class Audience
{
    public function __construct(
        public readonly AudienceKey $key,
        public readonly ?string $parameter,
        public readonly string $label,
    ) {}
}
