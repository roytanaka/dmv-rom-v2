@php
    // Links built for the render locale (ADR-0008): a French recipient gets the /fr/ twin. No token:
    // the pages enforce sign-in and the Applicant policy themselves.
    $localized = fn (string $route, array $parameters) => \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getURLFromRouteNameTranslated(
        app()->getLocale(), $route, $parameters,
    );
    $applicantUrl = $localized('routes.officer.members.applicants.show', ['applicant' => $payload['applicant']['id']]);
    $memberUrl = isset($payload['member']['id'])
        ? $localized('routes.officer.members.record', ['member' => $payload['member']['id']])
        : null;
@endphp

@component('mail::message')
# {{ __($key.'.heading') }}

{{-- Names render as-authored content, never through the lang files (ADR-0004). --}}
{{ __($key.'.intro', ['applicant' => $applicantName, 'group' => $payload['placement'] ?? '']) }}

@if (filled($payload['mentor'] ?? null))
{{ __('notices.applicant.mentor', ['mentor' => $payload['mentor']]) }}
@endif

@if (filled($payload['placement'] ?? null) && $payload['notice'] !== \App\Mail\ApplicantNotice::PLACED)
{{ __('notices.applicant.placement', ['group' => $payload['placement']]) }}
@endif

@if (filled($payload['reason'] ?? null))
{{ __('notices.applicant.reason', ['reason' => __('member.departure_reason.'.$payload['reason'])]) }}
@endif

@if ($memberUrl)
[{{ __('notices.applicant.view_member') }}]({{ $memberUrl }})
@endif

[{{ __('notices.applicant.view_applicant') }}]({{ $applicantUrl }})

{{ __('notices.applicant.footer') }}
@endcomponent
