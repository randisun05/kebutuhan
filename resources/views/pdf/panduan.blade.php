<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; line-height: 1.45; }
    h1 { font-size: 16px; border-bottom: 2px solid #0b3d91; padding-bottom: 4px; page-break-before: always; }
    h1.pertama { page-break-before: avoid; }
    h2 { font-size: 13px; margin-top: 14px; color: #0b3d91; }
    h3 { font-size: 11px; }
    table { width: 100%; border-collapse: collapse; margin: 6px 0; }
    th, td { border: 1px solid #999; padding: 3px 5px; text-align: left; }
    th { background: #e8edf5; }
    code { background: #f1f1f1; padding: 0 2px; }
    blockquote { border-left: 3px solid #0d6efd; margin: 6px 0; padding: 2px 8px; background: #f3f7ff; }
    img { max-width: 100%; }
    .sampul { text-align: center; padding-top: 180px; }
</style>
</head>
<body>
    <div class="sampul">
        <div style="font-size:26px; font-weight:bold; color:#0b3d91">SIMONKEB</div>
        <div style="font-size:14px">Panduan Pengguna</div>
        <div style="margin-top:8px">Sistem Informasi Monitoring Penyusunan Kebutuhan ASN</div>
        <div style="margin-top:30px; color:#555">Versi {{ $versi }} · dicetak {{ now()->translatedFormat('d F Y') }}</div>
    </div>
    @foreach($halaman as $i => $h)
        <h1>{{ $h['judul'] }}</h1>
        {!! preg_replace('/src="\/(img\/panduan\/[^"]+)"/', 'src="'.public_path('$1').'"', $h['html']) !!}
    @endforeach
</body>
</html>
