<?php

namespace App\Notifications;

use App\Models\Usulan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UsulanDiproses extends Notification
{
    use Queueable;

    public function __construct(
        public Usulan $usulan,
        public string $judul,
        public ?string $catatan = null,
        public ?string $oleh = null,
    ) {}

    public function via(object $notifiable): array
    {
        return config('simonkeb.notifikasi_email') && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[SIMONKEB] {$this->judul}: {$this->usulan->nomor}")
            ->greeting('Yth. '.$notifiable->name)
            ->line("{$this->judul} untuk usulan {$this->usulan->nomor} ({$this->usulan->perihal}).")
            ->line('Status saat ini: '.$this->usulan->status->label().'.');

        if ($this->catatan) {
            $mail->line('Catatan: '.$this->catatan);
        }

        return $mail->action('Buka usulan', url('/usulan/'.$this->usulan->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'judul' => $this->judul,
            'pesan' => "{$this->usulan->nomor} · {$this->usulan->perihal}",
            'catatan' => $this->catatan,
            'oleh' => $this->oleh,
            'status' => $this->usulan->status->value,
            'url' => '/usulan/'.$this->usulan->id,
        ];
    }
}
