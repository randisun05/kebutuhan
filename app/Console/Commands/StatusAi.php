<?php

namespace App\Console\Commands;

use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use Illuminate\Console\Command;

class StatusAi extends Command
{
    protected $signature = 'ai:status {--uji : kirim satu permintaan uji} {--pulihkan : lepaskan semua key dari masa istirahat}';

    protected $description = 'Tampilkan kondisi API key Gemini (bergiliran), opsional uji koneksi';

    public function handle(GeminiClient $gemini): int
    {
        if (! $gemini->aktif()) {
            $this->warn('Fitur AI belum aktif: isi GEMINI_API_KEY_1 s.d. GEMINI_API_KEY_5 (atau GEMINI_API_KEYS) di .env.');

            return self::FAILURE;
        }

        if ($this->option('pulihkan')) {
            $gemini->pulihkanSemua();
            $this->info('Semua key dilepaskan dari masa istirahat.');
        }

        if ($this->option('uji')) {
            try {
                $this->info('Jawaban uji: '.$gemini->generate('Balas hanya dengan kata: siap'));
            } catch (AiException $e) {
                $this->error($e->getMessage());
            }
        }

        $this->line('Model: '.config('gemini.model'));
        $this->table(['#', 'Key', 'Kondisi', 'Tersedia lagi', 'Alasan', 'Dipakai hari ini'], array_map(fn ($k) => [
            $k['nomor'], $k['key'], $k['tersedia'] ? 'siap' : 'istirahat', $k['istirahat_sampai'] ?? '-', $k['alasan'] ?? '-', $k['pemakaian_hari_ini'],
        ], $gemini->status()));

        return self::SUCCESS;
    }
}
