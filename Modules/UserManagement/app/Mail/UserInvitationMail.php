<?php

namespace Modules\UserManagement\App\Mail;

use Illuminate\Mail\Mailable;

class UserInvitationMail extends Mailable
{
    public array $invitation;
    public array $inviter;
    public string $frontendUrl;

    public function __construct(
        array $invitation,
        array $inviter,
        string $frontendUrl
    ) {
        $this->invitation = $invitation;
        $this->inviter = $inviter;
        $this->frontendUrl = $frontendUrl;
    }

    public function build()
    {
        return $this
            ->subject('You Have Been Invited')
            ->view('usermanagement::emails.user-invitation');
    }
}