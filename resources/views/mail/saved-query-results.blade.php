@php
    // The run page, built for the render locale (ADR-0008): a French recipient gets the /fr/ twin.
    // No token: the page enforces sign-in and member administration itself.
    $runUrl = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getURLFromRouteNameTranslated(
        app()->getLocale(),
        'routes.officer.members.queries.run',
        ['savedQuery' => $queryId],
    );
@endphp

@component('mail::message')
# {{ __('notices.saved_query_results.heading') }}

{{-- The query name, the note and the sender render as-authored content (ADR-0004). --}}
{{ __('notices.saved_query_results.intro', ['sender' => $sender, 'query' => $queryName]) }}

@if (filled($note))
{!! nl2br(e($note)) !!}
@endif

[{{ __('notices.saved_query_results.view') }}]({{ $runUrl }})

{{ __('notices.saved_query_results.footer') }}
@endcomponent
