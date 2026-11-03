{{-- The ROM-expiry notice (#943, ADR-0038 §9). Rendered in the recipient's locale. --}}
@component('mail::message')
{{ __('rom_expiry.greeting', ['name' => $name]) }}

{{ __('rom_expiry.body', ['date' => $expiry]) }}
@endcomponent
