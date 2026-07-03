<?php

namespace Modules\FrontendAuth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function build()
    {
        return $this->subject(
                'Your Magic Login Link'
            )
            ->view(
                'frontendauth::emails.magic-link'
            );
    }
}