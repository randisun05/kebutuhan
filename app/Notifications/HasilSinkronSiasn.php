<?php

namespace App\Notifications;

use App\Models\SiasnSyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Laporan hasil sinkronisasi SIASN: berhasil, gagal, dan jabatan/unor yang belum dipetakan. */
class HasilSinkronSiasn extends Notification
{
    use Queueable;

    public function __construct(public SiasnSyncLog $log, public string $instansi, public array $belumDipetakan = ['jabatan' => 0, 'unor' => 0]) {}

    public function via(object $notifiable): array
    {
        return config('simonkeb.notifikasi_email') && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function ringkas(): string
    {
        $l = $this->log;
        $jenis = $l->jenis === 'unor' ? 'unit organisasi' : 'data pegawai';

        if ($l->status === 'gagal') {
            return "Sinkron SIASN {$jenis} {$this->instansi} gagal: ".($l->pesan[0] ?? 'periksa log sinkronisasi').'.';
        }

        $teks = "Sinkron SIASN {$jenis} {$this->instansi}: {$l->berhasil} dari {$l->total} berhasil, {$l->gagal} gagal.";
        if ($this->belumDipetakan['jabatan'] || $this->belumDipetakan['unor']) {
            $teks .= " Belum dipetakan: {$this->belumDipetakan['jabatan']} jabatan, {$this->belumDipetakan['unor']} unor.";
        }

        return $teks;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('[SIMONKEB] Hasil sinkronisasi SIASN')->greeting('Yth. '.$notifiable->name)
            ->line($this->ringkas())->action('Buka Integrasi SIASN', url('/siasn?instansi_id='.$this->log->instansi_id));
    }

    public function toArray(object $notifiable): array
    {
        $perlu = $this->log->status === 'gagal' || $this->log->gagal > 0 || $this->belumDipetakan['jabatan'] || $this->belumDipetakan['unor'];

        return [
            'judul' => $this->log->status === 'gagal' ? 'Sinkronisasi SIASN gagal' : ($perlu ? 'Sinkronisasi SIASN selesai, ada yang perlu ditindaklanjuti' : 'Sinkronisasi SIASN selesai'),
            'pesan' => $this->ringkas(),
            'url' => '/siasn?instansi_id='.$this->log->instansi_id,
        ];
    }
}
