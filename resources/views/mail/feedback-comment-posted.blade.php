{{-- A new comment on a followed Feedback item (#860, ADR-0029 §15). Rendered in the Follower's locale. --}}
@component('mail::message')
# {{ __('feedback.follow.comment.heading', ['item' => $itemLabel]) }}

{{ __('feedback.follow.comment.intro', ['name' => $testerName]) }}

@if ($body !== null)
<x-mail::panel>
{!! nl2br(e($body)) !!}
</x-mail::panel>
@endif

@if ($hasImages)
{{ __('feedback.follow.comment.images') }}
@endif

{{ __('feedback.follow.comment.status', ['status' => $status]) }}

[{{ __('feedback.follow.view_item') }}]({{ $itemUrl }})

{{ __('feedback.follow.reply_on_site') }}

[{{ __('feedback.follow.stop_link') }}]({{ $stopUrl }})
@endcomponent
