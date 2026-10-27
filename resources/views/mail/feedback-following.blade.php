{{-- The "You're following" receipt (#857, ADR-0029 §15). Rendered in the Follower's locale. --}}
@component('mail::message')
# {{ __('feedback.follow.receipt.heading') }}

{{ __('feedback.follow.receipt.intro', ['item' => $itemLabel]) }}

[{{ __('feedback.follow.view_item') }}]({{ $itemUrl }})

{{ __('feedback.follow.reply_on_site') }}

[{{ __('feedback.follow.stop_link') }}]({{ $stopUrl }})
@endcomponent
