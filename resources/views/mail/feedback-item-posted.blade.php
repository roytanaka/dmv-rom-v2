{{-- A new Feedback item, to the notify address (ADR-0029 §16). Rendered in the app's default locale. --}}
{{-- Lines with user text go through MarkdownText::inert(), so typed markdown shows as text. --}}
@use('App\Support\Mail\MarkdownText')
@component('mail::message')
# {!! MarkdownText::inert(__('feedback.new_item.heading', ['item' => $itemLabel])) !!}

@if ($pageUrl !== null)
{!! MarkdownText::inert(__('feedback.new_item.intro', ['name' => $testerName, 'page' => $pageUrl])) !!}
@else
{!! MarkdownText::inert(__('feedback.new_item.intro_no_page', ['name' => $testerName])) !!}
@endif

<x-mail::panel>
{!! nl2br(MarkdownText::inert($message)) !!}
</x-mail::panel>

@if ($hasScreenshots)
{{ __('feedback.new_item.screenshots') }}
@endif

[{{ __('feedback.follow.view_item') }}]({{ $itemUrl }})

{{ __('feedback.follow.reply_on_site') }}
@endcomponent
