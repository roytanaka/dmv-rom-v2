<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * One Guest's copy of a Host's mail (#907, ADR-0034 §7). It is a Broadcast with one addition:
 * the link to the Invitation, in the Guest's locale. The Host's text stays as written.
 */
class InvitationMail extends BroadcastMail
{
    public function content(): Content
    {
        $url = LaravelLocalization::getURLFromRouteNameTranslated(
            app()->getLocale(),
            'routes.invitations.invitation',
            ['invitation' => $this->broadcast->invitation_id],
        );

        return new Content(
            htmlString: $this->broadcast->body.'<hr><p><a href="'.e($url).'">'.e(__('invitations.mail.link')).'</a></p>',
        );
    }
}
