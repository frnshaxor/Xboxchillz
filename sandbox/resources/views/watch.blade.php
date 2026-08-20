<!doctype html>
<html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $video->title }} · Arsip Layar Sandbox</title>
<style>body{margin:0;background:#101214;color:#eee;font:16px system-ui}main{max-width:960px;margin:auto;padding:56px 24px}.card{background:#191c20;border:1px solid #30343a;border-radius:12px;padding:24px}.notice{padding:14px;border:1px solid #74503e;background:#291f1a;border-radius:8px}input,button{padding:10px;border-radius:6px;border:1px solid #555}button{background:#d96b45;font-weight:700}a{color:#f4a582}.video{width:100%;max-height:70vh;background:#000}</style>
<main><p><a href="{{ route('catalog.index') }}">← Katalog</a></p><p>{{ $video->category?->name ?? 'Video' }} · {{ gmdate('i:s', $video->duration_seconds) }}</p><h1>{{ $video->title }}</h1><div class="card">
@if ($hasAccess && $video->source_path)
  <video class="video" controls src="{{ route('media.show', ['video' => $video, 'asset' => 'source.mp4']) }}"></video>
@elseif ($hasAccess)
  <p class="notice">Metadata video tersedia, tetapi media tidak disalin dari produksi. Unggah media khusus sandbox untuk menguji pemutaran.</p>
@else
  <p class="notice">Video terkunci. Masukkan token sandbox untuk membuka akses.</p>
  @error('token')<p class="notice">{{ $message }}</p>@enderror
  <form method="post" action="{{ route('access.store') }}">@csrf<input type="hidden" name="_token" value="{{ csrf_token() }}"><label>Token <input name="token" maxlength="64" required placeholder="XXXX-XXXX-XXXX"></label><button>Buka akses</button></form>
@endif
</div></main>
