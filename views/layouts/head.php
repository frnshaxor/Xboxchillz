<?php
/**
 * Head layout — <head> tag with all CSS, JS, meta, and Tailwind config.
 * Expected variables: $site, $desc, $cache_ver, $midtransEnabled, $midtransClientKey, $midtransMode, $isHttps
 */
$midtransEnabled   = $midtransEnabled   ?? false;
$midtransClientKey = $midtransClientKey ?? '';
$midtransMode      = $midtransMode      ?? 'sandbox';
$cache_ver         = $cache_ver         ?? '1';
$site              = $site              ?? 'Arsip Layar';
$desc              = $desc              ?? '';
$isHttps           = $isHttps           ?? false;
?><!doctype html>
<html lang="id" class="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#09090b">
<title><?= e($site) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,300..700,0..1,-25..0">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.css">
<style>.material-symbols-rounded{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;vertical-align:middle}</style>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography" onerror="console.warn('Tailwind CDN unavailable')"></script>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= e($cache_ver) ?>-midtrans-ui4">
<script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{display:['Fraunces','serif'],sans:['Geist','system-ui','sans-serif'],mono:['JetBrains Mono','ui-monospace']}}}}</script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js" defer onerror="console.warn('HLS.js CDN unavailable')"></script>
<script src="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.min.js" defer onerror="console.warn('Plyr CDN unavailable')"></script>
<script src="https://unpkg.com/vue@3.4.38/dist/vue.global.prod.js" defer onerror="console.warn('Vue CDN unavailable')"></script>
<?php if ($midtransEnabled && $midtransClientKey): ?>
<script src="<?= e(midtrans_snap_url($midtransMode)) ?>" data-client-key="<?= e($midtransClientKey) ?>" defer></script>
<?php endif; ?>
<script src="/assets/js/vue_enhance.js?v=<?= e($cache_ver) ?>-midtrans-ui4" defer></script>
</head>
