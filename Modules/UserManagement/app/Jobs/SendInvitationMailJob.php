<?php

namespace Modules\UserManagement\App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Modules\UserManagement\App\Mail\UserInvitationMail;

class SendInvitationMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $email;
    public array $invitation;
    public array $inviter;
    public string $frontendUrl;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $email,
        array $invitation,
        array $inviter,
        string $frontendUrl
    ) {
        $this->email = $email;
        $this->invitation = $invitation;
        $this->inviter = $inviter;
        $this->frontendUrl = $frontendUrl;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->email)
            ->send(
                new UserInvitationMail(
                    $this->invitation,
                    $this->inviter,
                    $this->frontendUrl
                )
            );
    }
}