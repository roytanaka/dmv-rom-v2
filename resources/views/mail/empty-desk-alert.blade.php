@php
    $orgTimezone = config('app.org_timezone');
    // The run day is already an org-wall-clock date (a bare Y-m-d, no zone) — the Shifts whose
    // org-clock date equals it are marked "today". Read as a plain date, never zone-shifted.
    $runDay = $runDate->toDateString();
    // A plain link to the Group's schedules section, built for the render locale (ADR-0008): a
    // French recipient gets the /fr/ twin. No token — the page enforces its own read audience.
    $scheduleUrl = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getURLFromRouteNameTranslated(
        app()->getLocale(),
        'routes.groups.show',
        ['group' => $groupSlug, 'section' => 'scheduling'],
    );
@endphp

@component('mail::message')
# {{ __('notices.empty_desk.heading') }}

{{ __('notices.empty_desk.intro', ['group' => $groupName]) }}

@component('mail::panel')
@foreach ($shifts as $shift)
@php
    $start = $shift['starts_at']->copy()->setTimezone($orgTimezone);
    $end = $shift['ends_at']->copy()->setTimezone($orgTimezone);
    // Date and times on the org wall clock; translatedFormat honours the render locale.
    $date = $start->translatedFormat('l, j F Y');
    $times = $start->translatedFormat('g:i A').' – '.$end->translatedFormat('g:i A');
    // The kind name is as-authored content — rendered directly, never through the lang files
    // (ADR-0004). A Shift always carries a watched kind here, so it is never blank.
    $isToday = $start->toDateString() === $runDay;
@endphp
- **{{ $date }}**@if ($isToday) — {{ __('notices.empty_desk.today') }}@endif<br>{{ $times }} · {{ $shift['kind'] }}
@endforeach
@endcomponent

{{ __('notices.empty_desk.footer') }}

[{{ __('notices.empty_desk.view_schedules') }}]({{ $scheduleUrl }})
@endcomponent
