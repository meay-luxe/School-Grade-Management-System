<?php
/* ============================================================
   GradeMS — Shared Header Partial
   File: shared/header.php
   ============================================================ */

require_once __DIR__ . '/../config/App.php';

$pageTitle  = $pageTitle  ?? 'GradeMS';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — GradeMS</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <!-- Font Awesome icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">

  <!-- Global styles -->
  <link rel="stylesheet" href="<?= App::url('/assets/css/global.css') ?>">

  <?php if (isset($extraCss)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($extraCss, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
</head>
<body>
<div id="app">

  <?php include __DIR__ . '/sidebar.php'; ?>

  <main id="main">
