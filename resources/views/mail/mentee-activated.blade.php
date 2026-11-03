@component('mail::message')
# {{ __('notices.mentee_activated.heading') }}

{{-- The name renders as-authored content, never through the lang files (ADR-0004). --}}
{{ __('notices.mentee_activated.intro', ['member' => $mentee->fullName()]) }}

{{ __('notices.mentee_activated.footer') }}
@endcomponent
