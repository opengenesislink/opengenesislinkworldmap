/* SPDX-License-Identifier: MPL-2.0 */
(() => {
  'use strict';
  const config = window.OGL_WORLDMAP;
  const $ = (id) => document.getElementById(id);
  const canvas = $('worldMap');
  const context = canvas.getContext('2d');
  const frame = $('mapFrame');
  const state = { x: config.centerX, y: config.centerY, cell: 30, regions: [], drag: null, generation: 0 };
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
    const left = Math.floor(wx(0)), right = Math.ceil(wx(w));
    const bottom = Math.floor(wy(h)), top = Math.ceil(wy(0));
    context.lineWidth = 1;
    for (let x = left; x <= right; x++) {
      context.strokeStyle = x % 10 === 0 ? '#304764' : '#182e48';
      context.beginPath(); const p = Math.round(sx(x)) + .5; context.moveTo(p, 0); context.lineTo(p, h); context.stroke();
    }
    for (let y = bottom; y <= top; y++) {
      context.strokeStyle = y % 10 === 0 ? '#304764' : '#182e48';
      context.beginPath(); const p = Math.round(sy(y)) + .5; context.moveTo(0, p); context.lineTo(w, p); context.stroke();
    }
    for (const region of state.regions) {
      const px = sx(region.x), py = sy(region.y + region.height);
      const rw = region.width * state.cell, rh = region.height * state.cell;
      if (px > w || py > h || px + rw < 0 || py + rh < 0) continue;
      context.fillStyle = region.width > 1 || region.height > 1 ? '#147e87' : '#185b92';
      context.fillRect(px + 1, py + 1, Math.max(1, rw - 2), Math.max(1, rh - 2));
      context.strokeStyle = region.width > 1 || region.height > 1 ? '#36dfdf' : '#4aaaf5';
      context.strokeRect(px + 1.5, py + 1.5, Math.max(1, rw - 3), Math.max(1, rh - 3));
      if (state.cell >= 16 && rw >= 65) {
        context.save();context.beginPath();context.rect(px + 5, py + 3, rw - 10, rh - 5);context.clip();
        context.fillStyle = '#e0f6ff';context.font = '600 11px system-ui';
        context.fillText(region.name || 'Unbenannt', px + 7, py + Math.min(18, rh / 2 + 4));context.restore();
      }
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
      message('Bitte die Karte vergrößern, um Regionsdaten abzurufen.');return;
    }
    controller?.abort();controller = new AbortController();const serial = ++state.generation;
    try {
      const url = new URL(config.api, window.location.href);
      Object.entries({ west, east, south, north }).forEach(([key, value]) => url.searchParams.set(key, value));
      const response = await fetch(url, {signal: controller.signal});
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Regionsdaten nicht verfügbar');
      if (serial !== state.generation) return;
      state.regions = data.regions || [];
      $('regionCount').textContent = state.regions.length.toLocaleString('de-DE');
      $('mapStatus').textContent = data.truncated ? 'Mehr als 2000 Treffer – bitte hineinzoomen' : state.regions.length + ' Regionen im Kartenausschnitt';
      $('connection').textContent = 'Datenbank verbunden';$('connection').className = 'live ready';
      message('');draw();
    } catch (error) {
      if (error.name === 'AbortError') return;
      $('connection').textContent = 'Datenquelle nicht erreichbar';$('connection').className = 'live offline';
      $('mapStatus').textContent = 'Keine Regionsdaten';
      message('Die Datenbank ist nicht eingerichtet oder derzeit nicht erreichbar. Die Karte bleibt bedienbar.');
    }
  }
  function scheduleLoad() { clearTimeout(pending);draw();pending = setTimeout(load, 180); }
  function centerAt(x, y) { state.x = clamp(x, -1000000, 1000000);state.y = clamp(y, -1000000, 1000000);scheduleLoad(); }
  function zoom(factor, px = pixelWidth()/2, py = pixelHeight()/2) {
    const x = wx(px), y = wy(py);
    state.cell = clamp(state.cell * factor, 8, 100);
    state.x = x - (px - pixelWidth()/2)/state.cell;
    state.y = y + (py - pixelHeight()/2)/state.cell;
    scheduleLoad();
  }
  function select(region) {
    const detail = $('regionDetails'); detail.replaceChildren();
    if (!region) { detail.textContent = 'Keine belegte Region an dieser Position.';return; }
    const title = document.createElement('div');title.className = 'regionTitle';title.textContent = region.name;detail.append(title);
    const dl = document.createElement('dl');
    [['Koordinaten', region.x + ' / ' + region.y],['Größe', region.sizeX + ' × ' + region.sizeY + ' m'],['Regionstyp',region.width > 1 || region.height > 1 ? 'Variable Region' : 'Standardregion'], ['Region-ID',region.id],['Server',region.serverURI || 'Nicht angegeben']].forEach(([label, value]) => {
      const dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = label;dd.textContent = value;dl.append(dt,dd);
    });detail.append(dl);
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
    select([...state.regions].reverse().find(region => x>=region.x && x<region.x+region.width && y>=region.y && y<region.y+region.height));
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
      for(const region of data.regions){const button=document.createElement('button');const label=document.createTextNode(region.name);const small=document.createElement('small');small.textContent='X '+ region.x + ' / Y ' + region.y;button.append(label,small);button.onclick=()=>{centerAt(region.x+region.width/2,region.y+region.height/2);select(region);};results.append(button);}
    } catch {results.textContent='Suche derzeit nicht verfügbar.';}
  });
  new ResizeObserver(()=>{draw();scheduleLoad();}).observe(frame);
  draw();scheduleLoad();
})();
