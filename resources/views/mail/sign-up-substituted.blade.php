@php
    $start = $notice['starts_at']->setTimezone(config('app.org_timezone'));
    $end = $notice['ends_at']->setTimezone(config('app.org_timezone'));
    // Date and times on the org wall clock; translatedFormat honours the render locale.
    $date = $start->translatedFormat('l, j F Y');
    $times = $start->translatedFormat('g:i A').' – '.$end->translatedFormat('g:i A');
    // Names, the Tour and the client are as-authored content (ADR-0004).
    $scheduleUrl = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getURLFromRouteNameTranslated(
        app()->getLocale(),
        'routes.groups.scheduling.show',
        ['group' => $notice['group_slug'], 'schedule' => $notice['schedule_id']],
    );
@endphp

@component('mail::message')
# {{ __('scheduling.substitution_email.heading') }}

{{ __('scheduling.substitution_email.intro', ['new' => $notice['substitute'], 'old' => $notice['previous'], 'group' => $notice['group_name']]) }}

@component('mail::panel')
**{{ $date }}**
{{ $times }}
@if ($notice['tour'])

{{ $notice['tour'] }}
@endif
@if ($notice['client'])

{{ $notice['client'] }}
@endif
@endcomponent

[{{ __('scheduling.substitution_email.view_schedule') }}]({{ $scheduleUrl }})
@endcomponent
