<?php
/* ============================================================
   GradeMS — Shared Header Partial
   File: shared/header.php
   Usage: include at the top of every authenticated page
          after Auth::requireRole(...)
   ============================================================ */

// $pageTitle should be set by the including page before this include.
$pageTitle = $pageTitle ?? 'GradeMS';
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

  <!-- Global styles -->
  <link rel="stylesheet" href="/assets/css/global.css">

  <?php if (isset($extraCss)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($extraCss, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
</head>
<body>
<div id="app">

  <?php include __DIR__ . '/sidebar.php'; ?>

  <main id="main">
