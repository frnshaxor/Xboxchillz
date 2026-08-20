<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Arsip Layar — Sandbox</title>
  <style>body{margin:0;background:#101214;color:#eee;font:16px system-ui}main{max-width:960px;margin:auto;padding:56px 24px}.eyebrow{color:#d96b45;text-transform:uppercase;letter-spacing:.12em;font-size:12px}h1{font-size:clamp(36px,7vw,70px);margin:8px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:18px}.card{background:#191c20;border:1px solid #30343a;border-radius:12px;padding:20px}a{color:#f4a582}.notice{padding:14px;border:1px solid #74503e;background:#291f1a;border-radius:8px}</style>
</head>
<body>
  <main>
    <div class="eyebrow">Laravel sandbox · tidak terhubung ke produksi</div><h1>Arsip Layar</h1><p>Katalog migrasi yang berjalan di lingkungan terisolasi.</p>
    <section class="grid">
    @forelse ($videos as $video)
      <article class="card"><div class="eyebrow">{{ $video->category?->name ?? 'Video' }}</div><h2>{{ $video->title }}</h2><p>{{ gmdate('i:s', $video->duration_seconds) }}</p><a href="{{ route('watch.show', $video) }}">Buka detail</a></article>
    @empty
      <p class="notice">Belum ada video sandbox. Media produksi tidak pernah dibaca dari aplikasi ini.</p>
    @endforelse
    </section>
  </main>
