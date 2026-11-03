{{-- The Renewal reminder summary (#942, ADR-0038 §9). Rendered in the recipient's locale. --}}
{{-- Names are as-authored and go through MarkdownText::inert(), so typed markdown shows as text. --}}
@use('App\Support\Mail\MarkdownText')
@component('mail::message')
# {{ __('notices.renewal_reminder_summary.heading') }}

{{ __('notices.renewal_reminder_summary.intro', ['year' => $seriesYear]) }}

@foreach ($mailed as $series => $names)
**{{ __('notices.renewal_reminder_summary.series.'.$series) }}** ({{ count($names) }})

@foreach ($names as $name)
- {!! MarkdownText::inert($name) !!}
@endforeach

@endforeach
@endcomponent
