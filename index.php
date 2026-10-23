<?php
/** SPDX-License-Identifier: MPL-2.0 */
declare(strict_types=1);
require_once __DIR__ . '/lib/map.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
$centerX = filter_var(mapSetting('OGL_CENTER_X', '5000'), FILTER_VALIDATE_INT);
$centerY = filter_var(mapSetting('OGL_CENTER_Y', '5000'), FILTER_VALIDATE_INT);
$settings = ['centerX' => $centerX !== false ? $centerX : 5000, 'centerY' => $centerY !== false ? $centerY : 5000, 'api' => './api/regions.php'];
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#071326">
<title>OpenGenesisLINK WorldMap</title>
<link rel="stylesheet" href="./assets/style.css?v=2">
</head>
<body>
<div class="app">
  <header class="topbar">
    <a class="brand brand-logo" href="./" aria-label="OpenGenesisLINK WorldMap – Startseite"><img src="./assets/worldmap-logo.webp?v=2" alt="OpenGenesisLINK WorldMap" width="800" height="266" decoding="async"></a>
    <div class="topright"><span class="live" id="connection">Verbindung wird geprüft</span><a href="https://opengenesislink.de/de/" target="_blank" rel="noopener noreferrer">Projektwebsite ↗</a></div>
  </header>
  <main class="workspace">
    <aside class="sidebar" aria-label="Kartensteuerung">
      <section><div class="eyebrow">EXPLORER / WORLD</div><h1>Entdecke virtuelle Welten.</h1><p class="sub">Eine interaktive Regionskarte für OpenGenesisLINK und OpenSimulator.</p></section>
      <section class="panel"><h2>Region suchen</h2><form id="searchForm" role="search"><label class="sr-only" for="regionSearch">Regionsname</label><div class="searchbar"><input id="regionSearch" name="q" placeholder="Regionsname eingeben …" minlength="2" maxlength="80" autocomplete="off"><button type="submit" aria-label="Suchen">⌕</button></div></form><div id="searchResults" class="results" aria-live="polite"></div></section>
      <section class="panel"><h2>Navigation</h2><div class="coordinates"><label>X <input id="coordX" type="number" step="1"></label><label>Y <input id="coordY" type="number" step="1"></label></div><button id="goButton" class="primary">Zu Koordinaten springen →</button><button id="homeButton" class="secondary">⌂ &nbsp; Kartenzentrum</button></section>
      <section class="panel details"><h2>Regionsdetails</h2><div id="regionDetails" class="detailPlaceholder">Region auf der Karte auswählen.</div></section>
      <footer class="sidefoot"><span>OpenGenesisLINK / WorldMap</span><span>Generation 1</span></footer>
    </aside>
    <div class="maparea"><div class="maptop"><div><span class="eyebrow">WORLD EXPLORER</span><div class="maptitle">Regionen <span id="regionCount">—</span></div></div><div class="maphelp">Ziehen: Verschieben <span>·</span> Mausrad: Zoomen <span>·</span> Klick: Details</div></div>
      <div class="mapframe" id="mapFrame"><canvas id="worldMap" aria-label="Interaktive Weltkarte der Regionen" role="img"></canvas><div id="mapMessage" class="mapmessage" role="status"></div><div class="mapcontrols"><button id="zoomIn" aria-label="Vergrößern">+</button><button id="zoomOut" aria-label="Verkleinern">−</button><button id="resetMap" aria-label="Zentrieren">◎</button></div><div class="mapcoords" id="mapCoords">X 5000 · Y 5000</div></div>
      <div class="legend"><span><i class="swatch occupied"></i> Standardregion</span><span><i class="swatch variable"></i> Variable Region</span><span><i class="swatch free"></i> Freie Position</span><span class="legendHint" id="mapStatus">Regionsdaten werden geladen …</span></div>
    </div>
  </main>
</div>
<script>window.OGL_WORLDMAP = <?= json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) ?>;</script>
<script src="./assets/app.js?v=2" defer></script>
</body>
</html>
