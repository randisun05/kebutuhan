<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
    .center { text-align: center; }
    table.grid { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .grid th, .grid td { border: 1px solid #444; padding: 3px 5px; }
    .grid th { background: #eee; }
    .num { text-align: right; }
</style>
</head>
<body>
    <div class="center"><b>LAMPIRAN KEPUTUSAN PENETAPAN KEBUTUHAN {{ $p->usulan->jenis_asn === 'pppk' ? 'PPPK' : 'PNS' }}</b></div>
    <div class="center">NOMOR {{ $p->nomor_sk }} TANGGAL {{ strtoupper($p->tanggal_sk->translatedFormat('d F Y')) }}</div>
    <div class="center" style="margin-top:6px"><b>{{ strtoupper($p->instansi->nama) }} — TAHUN ANGGARAN {{ $p->tahun }}</b></div>

    <table class="grid">
        <thead><tr><th>No</th><th>Unit Kerja</th><th>Jabatan</th><th>Kualifikasi Pendidikan</th><th>Jumlah</th></tr></thead>
        <tbody>
        @foreach($p->usulan->details as $i => $d)
            <tr><td class="num">{{ $i + 1 }}</td><td>{{ $d->unitKerja?->nama }}</td><td>{{ $d->jabatan?->nama }}</td><td>{{ $d->kualifikasi_pendidikan ?: '-' }}</td><td class="num">{{ $d->jumlah_ditetapkan }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><th colspan="4" class="num">Jumlah</th><th class="num">{{ $p->total_ditetapkan }}</th></tr></tfoot>
    </table>

    @if($p->keterangan)<p>Keterangan: {{ $p->keterangan }}</p>@endif
    <p style="margin-top:20px; color:#555">Usulan {{ $p->usulan->nomor }} · ditetapkan melalui SIMONKEB oleh {{ $p->penetap?->name ?? '-' }}. Dokumen ini adalah lampiran; keputusan yang sah adalah naskah SK yang ditandatangani pejabat berwenang.</p>
</body>
</html>
