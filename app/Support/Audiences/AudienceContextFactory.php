<?php

namespace App\Support\Audiences;

use App\Enums\ContextType;
use App\Models\Group;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use Illuminate\Http\Request;

/**
 * Builds an {@see AudienceContext} from a request's `context` type and a surface identifier —
 * the one place that turns the surface the browser names into the model the resolver reads
 * (ADR-0024 §6). Shared by the Audience read endpoints (#484), which name the surface `subject`,
 * and the Broadcast send path (#488), which reserves `subject` for the email subject line and so
 * names the surface `context_subject`; the key is a parameter. Both 404 the same way: an unknown
 * context type, or a surface identifier that resolves to no row, aborts.
 */
class AudienceContextFactory
{
    public function fromRequest(Request $request, string $subjectKey = 'subject'): AudienceContext
    {
        $type = ContextType::tryFrom((string) $request->input('context'));

        if ($type === null) {
            abort(404);
        }

        $subject = $request->input($subjectKey);

        return match ($type) {
            ContextType::Group => AudienceContext::group(
                Group::where('slug', $subject)->firstOrFail()
            ),
            ContextType::Schedule => AudienceContext::schedule(Schedule::findOrFail($subject)),
            ContextType::Shift => AudienceContext::shift(Shift::findOrFail($subject)),
            ContextType::Member => AudienceContext::member(Member::findOrFail($subject)),
            ContextType::Directory => AudienceContext::directory(),
        };
    }
}
