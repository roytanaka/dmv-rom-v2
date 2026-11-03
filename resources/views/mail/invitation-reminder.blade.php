@php
    // Stored as wall-clock values at the museum, so no zone conversion; translatedFormat
    // honours the render locale.
    $date = $invitation->date->translatedFormat('l, j F Y');
    $times = \Illuminate\Support\Carbon::parse($invitation->start_time)->translatedFormat('g:i A')
        .' – '.\Illuminate\Support\Carbon::parse($invitation->end_time)->translatedFormat('g:i A');
    $options = $guest->options->pluck('name')->join(', ');
    // Built for the render locale (ADR-0008). No token: a Guest signs in to reply.
    $invitationUrl = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getURLFromRouteNameTranslated(
        app()->getLocale(),
        'routes.invitations.invitation',
        ['invitation' => $invitation->id],
    );
@endphp

@component('mail::message')
# {{ __('invitations.reminder_email.heading') }}

{{ __('invitations.reminder_email.intro', ['member' => $guest->member->fullName()]) }}

@component('mail::panel')
**{{ $invitation->name }}**
{{ $date }}
{{ $times }}
{{ __('invitations.show.hosted_by', ['name' => $invitation->host->fullName()]) }}
@if ($options !== '')

{{ __('invitations.reminder_email.your_options', ['options' => $options]) }}
@endif
@endcomponent

{{ $invitation->description }}

[{{ __('invitations.reminder_email.view_invitation') }}]({{ $invitationUrl }})
@endcomponent
