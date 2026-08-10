@php
    $start = $shift->starts_at->copy()->setTimezone(config('app.org_timezone'));
    $end = $shift->ends_at->copy()->setTimezone(config('app.org_timezone'));
    // Date and times on the org wall clock; translatedFormat honours the render locale.
    $date = $start->translatedFormat('l, j F Y');
    $times = $start->translatedFormat('g:i A').' – '.$end->translatedFormat('g:i A');
    // Names and the ShiftKind label are as-authored content — rendered directly, never
    // through the lang files (ADR-0004).
    $memberName = trim($member->first_name.' '.$member->last_name);
    $groupName = $shift->schedule->group->name;
@endphp

@component('mail::message')
# {{ __('scheduling.cancellation_email.heading') }}

{{ __('scheduling.cancellation_email.intro', ['member' => $memberName, 'group' => $groupName]) }}

@component('mail::panel')
**{{ $date }}**
{{ $times }}
@if ($shift->kind)

{{ $shift->kind->name }}
@endif
@endcomponent

{{ __('scheduling.cancellation_email.footer') }}
@endcomponent
