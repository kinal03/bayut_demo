<?php

namespace Modules\UserManagement\App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable implements ShouldQueue
{
    use SerializesModels;

    public string $resetUrl;

    public function __construct(string $resetUrl)
    {
        $this->resetUrl = $resetUrl;
    }

    public function build()
    {
        return $this->subject('Reset Your Password')
            ->view('usermanagement::emails.forgot-password');
    }
}