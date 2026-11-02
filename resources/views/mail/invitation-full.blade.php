@php
    // A plain link to the Host's Guests page, built for the render locale (ADR-0008). No
    // token: the page enforces its own policy.
    $guestsUrl = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getURLFromRouteNameTranslated(
        app()->getLocale(),
        'routes.invitations.guests',
        ['invitation' => $invitationId],
    );
@endphp

@component('mail::message')
# {{ __('notices.invitation_full.heading') }}

{{ __('notices.invitation_full.intro', ['name' => $invitationName]) }}

[{{ __('notices.invitation_full.view_guests') }}]({{ $guestsUrl }})
@endcomponent
