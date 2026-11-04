{{-- A Renewal reminder (#942, ADR-0038 §9). The body is the member admin's text from Renewal --}}
{{-- settings, as authored, line breaks kept; MarkdownText::inert() keeps typed markdown as text. --}}
@use('App\Support\Mail\MarkdownText')
@component('mail::message')
{!! nl2br(MarkdownText::inert($body)) !!}
@endcomponent
