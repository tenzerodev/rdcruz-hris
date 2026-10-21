<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notice for every HRIS event: a subject, a few lines and a link. Shown in the HRIS
 * (bell icon) for users, and emailed when email notifications are on.
 */
class HrisNotice extends Notification
{
    use Queueable;

    /** @param  list<string>  $lines */
    public function __construct(public string $subject, public array $lines, public ?string $url = null, public string $action = 'Open HRIS', public bool $email = true) {}

    public function via(object $notifiable): array
    {
        $channels = $notifiable instanceof AnonymousNotifiable ? [] : ['database'];
        $wantsEmail = $notifiable instanceof AnonymousNotifiable || ($notifiable->notify_email ?? true);
        if ($this->email && $wantsEmail && Setting::get('notifications_enabled') === '1' && $notifiable->routeNotificationFor('mail', $this)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->greeting('Good day!');
        foreach ($this->lines as $line) {
            $mail->line($line);
        }
        if ($this->url) {
            $mail->action($this->action, $this->url);
        }

        return $mail->salutation('— '.Setting::get('agency_name').' HRIS');
    }

    /** @return array{subject: string, lines: list<string>, url: ?string} */
    public function toArray(object $notifiable): array
    {
        return ['subject' => $this->subject, 'lines' => $this->lines, 'url' => $this->url];
    }
}
