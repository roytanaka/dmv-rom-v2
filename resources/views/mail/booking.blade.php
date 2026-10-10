@php
    $key = $isConfirmation ? 'notices.booking_confirmation' : 'notices.booking_request';
    // The opened Schedule, built for the render locale (ADR-0008).
    $scheduleUrl = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getURLFromRouteNameTranslated(
        app()->getLocale(),
        'routes.groups.scheduling.show',
        ['group' => $group['slug'], 'schedule' => $scheduleId],
    );
@endphp

@component('mail::message')
# {{ __($key.'.heading') }}

{{ __($key.'.intro', ['group' => $group['name']]) }}

@component('mail::panel')
**{{ $booking['tour'] }}**

{{ $date }}
{{ $times }}

{{ __('notices.booking.client') }}: {{ $booking['client'] }}
{{ __('notices.booking.visitors') }}: {{ $booking['visitors'] }}
@if ($booking['leader'])
{{ __('notices.booking.leader') }}: {{ $booking['leader'] }}
@endif
@if ($booking['comments'])

{{ $booking['comments'] }}
@endif
@endcomponent

@if ($isConfirmation)
**{{ __('notices.booking_confirmation.docents') }}**

@foreach ($booking['docents'] as $docent)
- {{ $docent }}
@endforeach
@else
{{ trans_choice('notices.booking_request.seats_needed', $booking['seats_needed'], ['count' => $booking['seats_needed']]) }}
@endif

[{{ __($key.'.view_schedule') }}]({{ $scheduleUrl }})
@endcomponent
