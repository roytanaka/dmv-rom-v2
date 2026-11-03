{{-- The ROM-expiry notice summary (#943, ADR-0038 §9). Rendered in the recipient's locale. --}}
{{-- Names are as-authored and go through MarkdownText::inert(), so typed markdown shows as text. --}}
@use('App\Support\Mail\MarkdownText')
@component('mail::message')
# {{ __('rom_expiry.summary.heading') }}

{{ __('rom_expiry.summary.intro', ['count' => count($mailed)]) }}

@foreach ($mailed as $name)
- {!! MarkdownText::inert($name) !!}
@endforeach
@endcomponent
