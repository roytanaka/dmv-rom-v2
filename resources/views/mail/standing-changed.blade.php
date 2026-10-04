@php
    // Names render as-authored content — directly, never through the lang files (ADR-0004).
    $memberName = trim($member->first_name.' '.$member->last_name);
    // The new DMV-wide standing, resolved to its label in the render locale (chrome).
    $standingLabel = __('member.standing.'.$standing->value);
    // The effective date, in the render locale; translatedFormat honours it.
    $date = $effectiveDate->translatedFormat('j F Y');
    // A plain link to the Group's roster, built for the render locale (ADR-0008): a French
    // recipient gets the /fr/ twin. No token — the page enforces its own read audience.
    $rosterUrl = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getURLFromRouteNameTranslated(
        app()->getLocale(),
        'routes.groups.show',
        ['group' => $group->slug, 'section' => 'roster'],
    );
@endphp

@component('mail::message')
# {{ __('notices.standing_change.heading') }}

{{ __('notices.standing_change.intro', ['member' => $memberName, 'group' => $group->name, 'standing' => $standingLabel, 'date' => $date]) }}

{{ __('notices.standing_change.footer') }}

[{{ __('notices.standing_change.view_roster') }}]({{ $rosterUrl }})
@endcomponent
