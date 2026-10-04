@php
    $start = $shift->starts_at->copy()->setTimezone(config('app.org_timezone'));
    $end = $shift->ends_at->copy()->setTimezone(config('app.org_timezone'));
    // Date and times on the org wall clock; translatedFormat honours the render locale.
    $date = $start->translatedFormat('l, j F Y');
    $times = $start->translatedFormat('g:i A').' – '.$end->translatedFormat('g:i A');
    // Names, the Schedule name, and the ShiftKind label are as-authored content — rendered
    // directly, never through the lang files (ADR-0004).
    $memberName = trim($member->first_name.' '.$member->last_name);
    $groupName = $shift->schedule->group->name;
    $scheduleName = $shift->schedule->name;
    // A plain link to the Schedule, built for the render locale (ADR-0008): a French recipient
    // gets the /fr/ twin. No token — the page enforces its own read audience.
    $scheduleUrl = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getURLFromRouteNameTranslated(
        app()->getLocale(),
        'routes.groups.scheduling.show',
        ['group' => $shift->schedule->group->slug, 'schedule' => $shift->schedule->id],
    );
@endphp

@component('mail::message')
# {{ __('scheduling.reminder_email.heading') }}

{{ __('scheduling.reminder_email.intro', ['member' => $memberName, 'group' => $groupName]) }}

@component('mail::panel')
**{{ $date }}**
{{ $times }}
{{ $scheduleName }}
@if ($shift->kind)

{{ $shift->kind->name }}
@endif
@endcomponent

{{ __('scheduling.reminder_email.footer') }}

[{{ __('scheduling.reminder_email.view_schedule') }}]({{ $scheduleUrl }})
@endcomponent
