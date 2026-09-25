<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
    h1 { font-size: 14px; text-align: center; margin: 0 0 4px; }
    h2 { font-size: 11px; margin: 14px 0 4px; }
    table { width: 100%; border-collapse: collapse; }
    .info td { padding: 2px 4px; vertical-align: top; }
    .grid th, .grid td { border: 1px solid #555; padding: 3px 4px; }
    .grid th { background: #eee; }
    .num { text-align: right; }
    .muted { color: #555; }
    ul { margin: 0; padding-left: 14px; }
</style>
</head>
<body>
    <h1>INFORMASI JABATAN &amp; ANALISIS BEBAN KERJA</h1>
    <div style="text-align:center" class="muted">Tahun {{ $anjab->tahun }} · status {{ strtoupper($anjab->status) }}</div>

    <table class="info" style="margin-top:10px">
        <tr><td style="width:30%">1. Nama jabatan</td><td>: {{ $anjab->jabatan->nama }}</td></tr>
        <tr><td>2. Kode jabatan</td><td>: {{ $anjab->jabatan->kode }}</td></tr>
        <tr><td>3. Unit kerja</td><td>: {{ $anjab->unitKerja->nama }}@if($anjab->unitKerja->parent), {{ $anjab->unitKerja->parent->nama }}@endif</td></tr>
        <tr><td>4. Instansi</td><td>: {{ $anjab->instansi->nama }}</td></tr>
        <tr><td>5. Kelas jabatan</td><td>: {{ $anjab->kelas_jabatan ?? $anjab->jabatan->kelas_jabatan ?? '-' }}</td></tr>
        <tr><td>6. Ikhtisar jabatan</td><td>: {{ $anjab->ikhtisar_jabatan ?: '-' }}</td></tr>
    </table>

    <h2>7. Uraian tugas &amp; perhitungan beban kerja</h2>
    <table class="grid">
        <thead><tr><th>No</th><th>Uraian tugas</th><th>Hasil kerja</th><th>Volume</th><th>Norma waktu (menit)</th><th>Beban kerja (menit/th)</th><th>Pegawai</th></tr></thead>
        <tbody>
        @foreach($anjab->uraianTugas as $i => $t)
            <tr>
                <td class="num">{{ $i + 1 }}</td><td>{{ $t->uraian_tugas }}</td><td>{{ $t->hasil_kerja }}</td>
                <td class="num">{{ number_format($t->volume, 0, ',', '.') }} {{ $periode[$t->satuan_periode] ?? '' }}</td>
                <td class="num">{{ number_format($t->norma_waktu, 0, ',', '.') }}</td>
                <td class="num">{{ number_format($t->bebanKerja(), 0, ',', '.') }}</td>
                <td class="num">{{ number_format($t->bebanKerja() / $anjab->waktu_kerja_efektif, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><th colspan="5" class="num">Jumlah (waktu kerja efektif {{ number_format($anjab->waktu_kerja_efektif, 0, ',', '.') }} menit)</th>
                <th class="num">{{ number_format($anjab->total_beban_kerja, 0, ',', '.') }}</th><th class="num">{{ number_format($anjab->kebutuhan_hitung, 2, ',', '.') }}</th></tr>
            <tr><th colspan="6" class="num">Kebutuhan pegawai (dibulatkan)</th><th class="num">{{ $anjab->kebutuhan }}</th></tr>
        </tfoot>
    </table>
    <table class="info" style="margin-top:6px">
        <tr><td style="width:30%">Pegawai existing</td><td>: {{ $anjab->existing }} (selisih {{ $anjab->selisih > 0 ? '+' : '' }}{{ $anjab->selisih }})</td></tr>
        <tr><td>Efektivitas jabatan (EJ)</td><td>: {{ $anjab->ej ?? '-' }} @if($anjab->pej) — PEJ {{ $anjab->pej['nilai'] }} ({{ $anjab->pej['label'] }}) @endif</td></tr>
    </table>

    @php $n = 8; @endphp
    @foreach($fields as $key => $meta)
        @php $isi = $anjab->informasi[$key] ?? null; @endphp
        @if(! empty($isi))
            <h2>{{ $n++ }}. {{ $meta['label'] }}</h2>
            @if(is_array($isi))
                <ul>@foreach($isi as $item)<li>{{ $item }}</li>@endforeach</ul>
            @else
                <div>{{ $isi }}</div>
            @endif
        @endif
    @endforeach

    <p class="muted" style="margin-top:16px">Disusun berdasarkan pedoman Analisis Jabatan dan Analisis Beban Kerja (PermenPANRB Nomor 1 Tahun 2020). Dicetak dari SIMONKEB {{ now()->format('d-m-Y H:i') }}.</p>
</body>
</html>
