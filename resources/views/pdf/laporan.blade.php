<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111; }
    h1 { font-size: 13px; margin: 0; }
    .sub { color: #555; margin: 2px 0 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #777; padding: 3px 4px; vertical-align: top; }
    th { background: #e8edf5; }
    tfoot td { font-weight: bold; background: #f3f3f3; }
    .num { text-align: right; }
</style>
</head>
<body>
    <h1>{{ strtoupper($judul) }}</h1>
    <div class="sub">{{ $subjudul }} · dicetak oleh {{ $oleh }}</div>
    <table>
        <thead><tr><th>No</th>@foreach($kolom as $k)<th>{{ $k }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($baris as $i => $row)
            <tr><td class="num">{{ $i + 1 }}</td>@foreach($row as $v)<td class="{{ is_numeric($v) ? 'num' : '' }}">{{ is_numeric($v) && ! is_string($v) ? number_format($v, is_float($v) ? 1 : 0, ',', '.') : $v }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($kolom) + 1 }}" style="text-align:center">Tidak ada data.</td></tr>
        @endforelse
        </tbody>
        @if($total)
            <tfoot><tr><td></td>@foreach($total as $v)<td class="{{ is_numeric($v) ? 'num' : '' }}">{{ is_numeric($v) ? number_format($v, is_float($v) ? 1 : 0, ',', '.') : $v }}</td>@endforeach</tr></tfoot>
        @endif
    </table>
    <p class="sub" style="margin-top:8px">SIMONKEB — Sistem Informasi Monitoring Penyusunan Kebutuhan ASN</p>
</body>
</html>
