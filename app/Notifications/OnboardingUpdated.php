<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class OnboardingUpdated extends Notification
{
    public function __construct(
        public int $onboardingId,
        public string $title,
        public string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'onboarding_id' => $this->onboardingId,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}