/* SPDX-License-Identifier: MPL-2.0 */
(() => {
  'use strict';
  const config = window.OGL_WORLDMAP;
  const $ = (id) => document.getElementById(id);
  const canvas = $('worldMap');
  const context = canvas.getContext('2d');
  const frame = $('mapFrame');
  const state = { x: config.centerX, y: config.centerY, cell: 30, regions: [], bounds: null, truncated: false, selected: null, drag: null, generation: 0 };
  let pending = 0;
  let controller = null;
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const pixelWidth = () => canvas.clientWidth;
  const pixelHeight = () => canvas.clientHeight;
  const sx = (x) => (x - state.x) * state.cell + pixelWidth() / 2;
  const sy = (y) => (state.y - y) * state.cell + pixelHeight() / 2;
  const wx = (x) => state.x + (x - pixelWidth() / 2) / state.cell;
  const wy = (y) => state.y - (y - pixelHeight() / 2) / state.cell;
  const format = (value) => Math.round(value).toLocaleString('de-DE');
  function syncInputs() {
    if (document.activeElement !== $('coordX')) $('coordX').value = Math.round(state.x);
    if (document.activeElement !== $('coordY')) $('coordY').value = Math.round(state.y);
    $('mapCoords').textContent = 'X ' + format(state.x) + ' · Y ' + format(state.y) + ' · ' + Math.round(state.cell) + ' px / Region';
  }
  function draw() {
    const w = pixelWidth(), h = pixelHeight();
    if (!w || !h) return;
    const ratio = Math.min(devicePixelRatio || 1, 2);
    if (canvas.width !== Math.round(w * ratio) || canvas.height !== Math.round(h * ratio)) {
      canvas.width = Math.round(w * ratio); canvas.height = Math.round(h * ratio);
    }
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.clearRect(0, 0, w, h);
    context.fillStyle = '#081a31'; context.fillRect(0, 0, w, h);
    // Empty land is green only after a complete and successful GridService query.
    if (state.bounds && !state.truncated) {
      const b = state.bounds;
      context.fillStyle = '#145238';
      context.fillRect(sx(b.west), sy(b.north), (b.east - b.west) * state.cell, (b.north - b.south) * state.cell);
    }
    const left = Math.floor(wx(0)), right = Math.ceil(wx(w));
    const bottom = Math.floor(wy(h)), top = Math.ceil(wy(0));
    context.lineWidth = 1;
    for (let x = left; x <= right; x++) {
      context.strokeStyle = x % 10 === 0 ? '#4c755e' : '#2b5140';
      context.beginPath(); const p = Math.round(sx(x)) + .5; context.moveTo(p, 0); context.lineTo(p, h); context.stroke();
    }
    for (let y = bottom; y <= top; y++) {
      context.strokeStyle = y % 10 === 0 ? '#4c755e' : '#2b5140';
      context.beginPath(); const p = Math.round(sy(y)) + .5; context.moveTo(0, p); context.lineTo(w, p); context.stroke();
    }
    for (const region of state.regions) {
      const px = sx(region.x), py = sy(region.y + region.height);
      const rw = region.width * state.cell, rh = region.height * state.cell;
      if (px > w || py > h || px + rw < 0 || py + rh < 0) continue;
      context.fillStyle = '#af3544';
      context.fillRect(px + 1, py + 1, Math.max(1, rw - 2), Math.max(1, rh - 2));
      context.strokeStyle = '#ff7f8b';
      if (region.width > 1 || region.height > 1) context.setLineDash([5, 3]);
      context.strokeRect(px + 1.5, py + 1.5, Math.max(1, rw - 3), Math.max(1, rh - 3));
      context.setLineDash([]);
      if (state.cell >= 16 && rw >= 65) {
        context.save();context.beginPath();context.rect(px + 5, py + 3, rw - 10, rh - 5);context.clip();
        context.fillStyle = '#fff4f6';context.font = '600 11px system-ui';
        context.fillText(region.name || 'Unbenannt', px + 7, py + Math.min(18, rh / 2 + 4));context.restore();
      }
    }
    if (state.selected) {
      context.lineWidth = 2; context.strokeStyle = '#ffffff';
      context.strokeRect(sx(state.selected.x) + 2, sy(state.selected.y + 1) + 2, Math.max(1, state.cell - 4), Math.max(1, state.cell - 4));
    }
    context.fillStyle = '#65b7fd'; context.beginPath();context.arc(w / 2, h / 2, 3.5, 0, Math.PI * 2); context.fill();
    syncInputs();
  }
  function message(text) { $('mapMessage').textContent = text; $('mapMessage').classList.toggle('visible', Boolean(text)); }
  async function load() {
    const width = Math.ceil(pixelWidth() / state.cell / 2) + 3;
    const height = Math.ceil(pixelHeight() / state.cell / 2) + 3;
    const west = Math.floor(state.x - width), east = Math.ceil(state.x + width);
    const south = Math.floor(state.y - height), north = Math.ceil(state.y + height);
    if (east - west > 256 || north - south > 256) {
      controller?.abort(); ++state.generation;
      message('Bitte vergrößern, um freie Felder zuverlässig zu ermitteln.');return;
    }
    controller?.abort();controller = new AbortController();const serial = ++state.generation;
    try {
      const url = new URL(config.api, window.location.href);
      Object.entries({ west, east, south, north }).forEach(([key, value]) => url.searchParams.set(key, value));
      const response = await fetch(url, {signal: controller.signal});
      const data = await response.json();
      if (!response.ok) {
        const error = new Error(data.error || 'Regionsdaten nicht verfügbar');
        error.code = data.code || 'data_source_error';
        throw error;
      }
      if (serial !== state.generation) return;
      state.regions = Array.isArray(data.regions) ? data.regions : [];
      state.bounds = {west, east, south, north};
      state.truncated = Boolean(data.truncated);
      $('regionCount').textContent = state.regions.length.toLocaleString('de-DE');
      $('mapStatus').textContent = data.truncated ? 'Zu viele Regionen – bitte hineinzoomen; freie Felder ungeprüft' : state.regions.length + ' belegte Regionen · grüne Felder sind nicht registriert';
      $('connection').textContent = 'Datenbank verbunden';$('connection').className = 'live ready';
      message('');draw();
    } catch (error) {
      if (error.name === 'AbortError' || serial !== state.generation) return;
      state.bounds = null; state.regions = []; state.truncated = false;
      $('connection').textContent = error.code === 'configuration_required' ? 'Datenbank nicht konfiguriert' : 'Datenquelle nicht erreichbar';
      $('connection').className = 'live offline';
      $('mapStatus').textContent = 'Keine Regionsdaten';
      message(error.message && error.message !== 'Failed to fetch'
        ? error.message
        : 'Regions-API nicht erreichbar. Bitte PHP, Webserver und API-Pfad prüfen.');
      draw();
    }
  }
  function scheduleLoad() {
    clearTimeout(pending); state.bounds = null; state.regions = []; state.truncated = false;
    state.selected = null; popup.hidden = true; draw(); pending = setTimeout(load, 180);
  }
  function centerAt(x, y) { state.x = clamp(x, -1000000, 1000000);state.y = clamp(y, -1000000, 1000000);scheduleLoad(); }
  function zoom(factor, px = pixelWidth()/2, py = pixelHeight()/2) {
    const x = wx(px), y = wy(py);
    state.cell = clamp(state.cell * factor, 8, 100);
    state.x = x - (px - pixelWidth()/2)/state.cell;
    state.y = y + (py - pixelHeight()/2)/state.cell;
    scheduleLoad();
  }

  const popup = $('mapPopup');
  const popupBody = $('popupBody');
  function isVerified(x, y) {
    const b = state.bounds;
    return !!b && !state.truncated && x >= b.west && x < b.east && y >= b.south && y < b.north;
  }
  function closePopup() { popup.hidden = true; state.selected = null; draw(); }
  $('popupClose').onclick = closePopup;

  function fillDetails(node, region, cell, verified, asPopup) {
    node.replaceChildren();
    const occupied = Boolean(region);
    const status = occupied ? 'Belegt' : verified ? 'Frei' : 'Nicht geprüft';
    if (asPopup) {
      const badge = document.createElement('span');
      badge.className = 'popup-status ' + (occupied ? 'status-occupied' : verified ? 'status-free' : 'status-unknown');
      badge.textContent = status; node.append(badge);
    }
    const heading = document.createElement(asPopup ? 'h3' : 'div');
    if (!asPopup) heading.className = 'regionTitle';
    heading.textContent = occupied ? (region.name || 'Belegte Region') : verified ? 'Freies Kartenfeld' : 'Noch nicht geprüft';
    node.append(heading);
    const info = [['Koordinaten', cell.x + ' / ' + cell.y]];
    if (occupied) {
      if (region.x !== cell.x || region.y !== cell.y) info.push(['Regionsursprung', region.x + ' / ' + region.y]);
      info.push(['Region', region.name || 'Unbenannt']);
      info.push(['Größe', region.sizeX + ' × ' + region.sizeY + ' m']);
      info.push(['Typ', region.width > 1 || region.height > 1 ? 'VarRegion' : 'Standardregion']);
      info.push(['Besitzer', region.ownerName || (region.ownerId ? 'Name nicht verfügbar' : 'Nicht hinterlegt')]);
      if (region.ownerId) info.push(['Besitzer-ID', region.ownerId]);
      if (region.id) info.push(['Region-ID', region.id]);
      if (region.serverURI) info.push(['Server', region.serverURI]);
    } else if (verified) {
      info.push(['Feldgröße', '256 × 256 m']);
    }
    const dl = document.createElement('dl');
    for (const [key, value] of info) {
      const dt = document.createElement('dt'), dd = document.createElement('dd');
      dt.textContent = key; dd.textContent = value;
      if (key === 'Koordinaten') dd.className = 'coord';
      dl.append(dt, dd);
    }
    node.append(dl);
    if (!occupied && asPopup) {
      const hint = document.createElement('p'); hint.className = 'popup-info';
      hint.textContent = verified
        ? 'Hier ist keine Region registriert. Ob eine Region angelegt werden darf, entscheidet der Gridbetreiber.'
        : 'Der Kartenausschnitt ist noch nicht vollständig geladen. Ein freies Feld kann nicht bestätigt werden.';
      node.append(hint);
    }
  }
  function showPopup(region, cell, px, py) {
    const verified = isVerified(cell.x, cell.y);
    fillDetails(popupBody, region, cell, verified, true);
    fillDetails($('regionDetails'), region, cell, verified, false);
    popup.hidden = false;
    popup.style.left = clamp(px + 12, 8, Math.max(8, frame.clientWidth - popup.offsetWidth - 8)) + 'px';
    popup.style.top = clamp(py + 12, 8, Math.max(8, frame.clientHeight - popup.offsetHeight - 8)) + 'px';
    state.selected = cell; draw();
  }
  let dragged = false;
  canvas.addEventListener('pointerdown', (event) => { canvas.setPointerCapture(event.pointerId);state.drag = {px:event.clientX,py:event.clientY,x:state.x,y:state.y};dragged=false; });
  canvas.addEventListener('pointermove', (event) => {
    if (!state.drag) return;
    const dx = event.clientX-state.drag.px,dy = event.clientY-state.drag.py;
    if (Math.abs(dx)+Math.abs(dy)>4) dragged = true;
    if (dragged) { state.x = state.drag.x-dx/state.cell;state.y=state.drag.y+dy/state.cell;draw(); }
  });
  canvas.addEventListener('pointerup', (event) => {
    if (!state.drag) return;state.drag=null;
    if (dragged) {scheduleLoad();return;}
    const bounds = canvas.getBoundingClientRect();const x=wx(event.clientX-bounds.left),y=wy(event.clientY-bounds.top);
    const cell = {x:Math.floor(x), y:Math.floor(y)};
    const region = [...state.regions].reverse().find(region => cell.x>=region.x && cell.x<region.x+region.width && cell.y>=region.y && cell.y<region.y+region.height);
    showPopup(region || null, cell, event.clientX-bounds.left, event.clientY-bounds.top);
  });
  canvas.addEventListener('pointercancel', () => {state.drag=null;scheduleLoad();});
  canvas.addEventListener('wheel', (event) => {event.preventDefault();const bounds=canvas.getBoundingClientRect();zoom(event.deltaY < 0?1.15:1/1.15,event.clientX-bounds.left,event.clientY-bounds.top);}, {passive:false});
  $('zoomIn').onclick = () => zoom(1.3);$('zoomOut').onclick=()=>zoom(1/1.3);
  $('homeButton').onclick = $('resetMap').onclick = () => centerAt(config.centerX,config.centerY);
  $('goButton').onclick = () => centerAt(Number($('coordX').value)||0,Number($('coordY').value)||0);
  $('searchForm').addEventListener('submit',async(event)=>{
    event.preventDefault();const term=$('regionSearch').value.trim();if(term.length<2)return;
    const results=$('searchResults');results.replaceChildren();results.textContent='Suche läuft …';
    try {const url=new URL(config.api,location.href);url.searchParams.set('q',term);const response=await fetch(url);const data=await response.json();if(!response.ok) throw Error(data.error||'Suche fehlgeschlagen');
      results.replaceChildren();if(!data.regions.length){results.innerHTML='<div class="noresult">Keine Regionen gefunden.</div>';return;}
      for(const region of data.regions){const button=document.createElement('button');const label=document.createTextNode(region.name);const small=document.createElement('small');small.textContent='X '+ region.x + ' / Y ' + region.y;button.append(label,small);button.onclick=()=>{centerAt(region.x+region.width/2,region.y+region.height/2);showPopup(region,{x:region.x,y:region.y},pixelWidth()/2,pixelHeight()/2);};results.append(button);}
    } catch {results.textContent='Suche derzeit nicht verfügbar.';}
  });
  canvas.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closePopup();
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      const cell = {x:Math.floor(state.x),y:Math.floor(state.y)};
      const region = [...state.regions].reverse().find(r=>cell.x>=r.x&&cell.x<r.x+r.width&&cell.y>=r.y&&cell.y<r.y+r.height);
      showPopup(region||null,cell,pixelWidth()/2,pixelHeight()/2);
    }
  });
  new ResizeObserver(()=>{draw();scheduleLoad();}).observe(frame);
  draw();scheduleLoad();
})();
