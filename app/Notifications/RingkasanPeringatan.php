<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RingkasanPeringatan extends Notification
{
    use Queueable;

    public function __construct(public int $jumlah, public array $judul) {}

    public function via(object $notifiable): array
    {
        return config('simonkeb.notifikasi_email') && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject("[SIMONKEB] {$this->jumlah} peringatan penting baru")
            ->greeting('Yth. '.$notifiable->name);
        foreach ($this->judul as $j) {
            $mail->line('• '.$j);
        }

        return $mail->action('Lihat peringatan', url('/peringatan'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'judul' => "{$this->jumlah} peringatan penting baru",
            'pesan' => implode(' · ', array_slice($this->judul, 0, 3)).($this->jumlah > 3 ? ' …' : ''),
            'url' => '/peringatan',
        ];
    }
}
