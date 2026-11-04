{{-- The renewal Notice (#940, ADR-0038 §9). Rendered in the recipient's locale. --}}
{{-- Names and typed values are as-authored and go through MarkdownText::inert(), so typed markdown shows as text. --}}
@use('App\Support\Mail\MarkdownText')
@use('App\Mail\RenewalSubmitted')
@component('mail::message')
# {{ __('notices.renewal_submitted.heading') }}

{!! MarkdownText::inert(__($payload['update'] ? 'notices.renewal_submitted.intro_update' : 'notices.renewal_submitted.intro', ['member' => $memberName, 'year' => $payload['year']])) !!}

@if (filled($payload['member']['membership_number'] ?? null))
{{ __('notices.renewal_submitted.membership_number') }}: {!! MarkdownText::inert($payload['member']['membership_number']) !!}
@endif

@if ($payload['expired'])
**{{ __('notices.renewal_submitted.expired') }}**
@endif

@if ($payload['changes'] !== [])
**{{ __('notices.renewal_submitted.changes') }}**

@foreach ($payload['changes'] as $change)
- {{ __('records.field.'.$change['field']) }}: {!! MarkdownText::inert(__('notices.renewal_submitted.from_to', ['from' => $change['from'], 'to' => $change['to']])) !!}
@endforeach
@endif

@if ($payload['category_change'])
{{ __('notices.renewal_submitted.category_change', [
    'from' => RenewalSubmitted::categoryLabel($payload['category_change']['from']),
    'to' => RenewalSubmitted::categoryLabel($payload['category_change']['to']),
]) }}
@endif
@endcomponent
