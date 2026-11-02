@php
    // A plain link to the Host's Replies page, built for the render locale (ADR-0008). No
    // token: the page enforces its own policy.
    $repliesUrl = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getURLFromRouteNameTranslated(
        app()->getLocale(),
        'routes.invitations.replies',
        ['invitation' => $invitationId],
    );
@endphp

@component('mail::message')
# {{ __('notices.invitation_full.heading') }}

{{ __('notices.invitation_full.intro', ['name' => $invitationName]) }}

[{{ __('notices.invitation_full.view_replies') }}]({{ $repliesUrl }})
@endcomponent
