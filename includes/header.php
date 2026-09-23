<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($judul ?? 'Sembako Bersaudara') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <?php
  // Penanda versi dari waktu file terakhir diubah, supaya browser selalu memuat
  // CSS terbaru setelah tampilan diperbarui (bukan versi lama dari cache).
  $cssDir = dirname(__DIR__) . '/assets/css/';
  $cssV   = @filemtime($cssDir . 'style.css') ?: time();
  ?>
  <link rel="stylesheet" href="<?= ROOT ?>assets/css/style.css?v=<?= $cssV ?>">
<?php foreach (($extraCss ?? []) as $css): $v = @filemtime($cssDir . $css) ?: time(); ?>
  <link rel="stylesheet" href="<?= ROOT ?>assets/css/<?= e($css) ?>?v=<?= $v ?>">
<?php endforeach; ?>
</head>
<body>
