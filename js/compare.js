/* Image Comparison — synchronized zoom & pan viewer.
   Dependency-free. A single transform (scale + translate) is mirrored onto
   both image canvases; each canvas sits at its own viewport origin, so the
   same image-space region is shown for both photos in either mode. */
(function () {
  'use strict';

  var app = document.querySelector('.ic-app');
  if (!app) {
    return;
  }

  var modeButtons = app.querySelectorAll('.ic-mode-btn');
  var stage = app.querySelector('.ic-stage');

  // ---- mode switching (available even before/without a viewer) ----------
  function setMode(mode) {
    if (mode !== 'slider') {
      mode = 'sidebyside';
    }
    app.setAttribute('data-mode', mode);
    if (stage) {
      stage.classList.remove('ic-mode-sidebyside', 'ic-mode-slider');
      stage.classList.add('ic-mode-' + mode);
    }
    for (var i = 0; i < modeButtons.length; i++) {
      modeButtons[i].setAttribute(
        'aria-pressed',
        modeButtons[i].getAttribute('data-mode') === mode ? 'true' : 'false'
      );
    }
  }
  for (var m = 0; m < modeButtons.length; m++) {
    (function (btn) {
      btn.addEventListener('click', function () {
        setMode(btn.getAttribute('data-mode'));
      });
    })(modeButtons[m]);
  }

  wireSaveDelete();

  if (!stage) {
    return; // no pair selected yet — nothing to zoom/pan
  }

  // ---- viewer state -----------------------------------------------------
  var canvases = stage.querySelectorAll('.ic-canvas');
  var leftImg = stage.querySelector('.ic-img[data-side="left"]');
  var rightImg = stage.querySelector('.ic-img[data-side="right"]');
  var leftVp = stage.querySelector('.ic-viewport-left');
  var rightVp = stage.querySelector('.ic-viewport-right');
  var divider = stage.querySelector('.ic-divider');
  var zoomLevel = app.querySelector('.ic-zoom-level');

  var scale = 1;
  var tx = 0;
  var ty = 0;
  var refW = 1;
  var refH = 1;

  function clampScale(s) {
    return Math.min(40, Math.max(0.05, s));
  }

  function apply() {
    var t = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')';
    for (var i = 0; i < canvases.length; i++) {
      canvases[i].style.transform = t;
    }
    if (zoomLevel) {
      zoomLevel.textContent = Math.round(scale * 100) + '%';
    }
  }

  function fitToViewport() {
    var vw = leftVp.clientWidth;
    var vh = leftVp.clientHeight;
    var f = Math.min(vw / refW, vh / refH);
    if (!isFinite(f) || f <= 0) {
      f = 1;
    }
    scale = f;
    tx = (vw - refW * scale) / 2;
    ty = (vh - refH * scale) / 2;
    apply();
  }

  function actualSize() {
    var vw = leftVp.clientWidth;
    var vh = leftVp.clientHeight;
    scale = 1;
    tx = (vw - refW) / 2;
    ty = (vh - refH) / 2;
    apply();
  }

  // viewport-local point under the cursor; in side-by-side we measure
  // against the half the cursor is actually over so the pinned point is
  // correct in both panes (they share the same local coordinate space)
  function localPoint(clientX, clientY) {
    var vp = leftVp;
    if (app.getAttribute('data-mode') === 'sidebyside') {
      var rl = leftVp.getBoundingClientRect();
      if (clientX >= rl.right) {
        vp = rightVp;
      }
    }
    var r = vp.getBoundingClientRect();
    return { x: clientX - r.left, y: clientY - r.top };
  }

  function zoomAt(cx, cy, factor) {
    var ns = clampScale(scale * factor);
    var k = ns / scale;
    tx = cx - k * (cx - tx);
    ty = cy - k * (cy - ty);
    scale = ns;
    apply();
  }

  // ---- pointer interaction: pan + pinch --------------------------------
  var pointers = {};
  var lastDist = 0;
  var draggingDivider = false;

  function pointerCount() {
    return Object.keys(pointers).length;
  }

  stage.addEventListener('pointerdown', function (e) {
    if (divider && (e.target === divider || e.target.parentNode === divider)) {
      draggingDivider = true;
      try { divider.setPointerCapture(e.pointerId); } catch (err) {}
      moveDivider(e.clientX);
      e.preventDefault();
      return;
    }
    pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
    try { stage.setPointerCapture(e.pointerId); } catch (err) {}
    if (pointerCount() === 2) {
      lastDist = pinchDistance();
    }
    e.preventDefault();
  });

  stage.addEventListener('pointermove', function (e) {
    if (draggingDivider) {
      moveDivider(e.clientX);
      return;
    }
    if (!pointers[e.pointerId]) {
      return;
    }
    var prev = pointers[e.pointerId];
    var dx = e.clientX - prev.x;
    var dy = e.clientY - prev.y;
    pointers[e.pointerId] = { x: e.clientX, y: e.clientY };

    if (pointerCount() === 2) {
      var dist = pinchDistance();
      if (lastDist > 0) {
        var mid = pinchMidpoint();
        var p = localPoint(mid.x, mid.y);
        zoomAt(p.x, p.y, dist / lastDist);
      }
      lastDist = dist;
    } else {
      tx += dx;
      ty += dy;
      apply();
    }
  });

  function endPointer(e) {
    if (draggingDivider) {
      draggingDivider = false;
      return;
    }
    delete pointers[e.pointerId];
    if (pointerCount() < 2) {
      lastDist = 0;
    }
  }
  stage.addEventListener('pointerup', endPointer);
  stage.addEventListener('pointercancel', endPointer);

  function pinchDistance() {
    var ks = Object.keys(pointers);
    var a = pointers[ks[0]];
    var b = pointers[ks[1]];
    return Math.hypot(a.x - b.x, a.y - b.y);
  }
  function pinchMidpoint() {
    var ks = Object.keys(pointers);
    var a = pointers[ks[0]];
    var b = pointers[ks[1]];
    return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
  }

  // ---- wheel zoom -------------------------------------------------------
  stage.addEventListener('wheel', function (e) {
    e.preventDefault();
    var p = localPoint(e.clientX, e.clientY);
    var factor = e.deltaY < 0 ? 1.12 : 1 / 1.12;
    zoomAt(p.x, p.y, factor);
  }, { passive: false });

  // ---- divider (slider mode) -------------------------------------------
  function moveDivider(clientX) {
    var r = stage.getBoundingClientRect();
    var pct = ((clientX - r.left) / r.width) * 100;
    pct = Math.min(100, Math.max(0, pct));
    app.style.setProperty('--ic-split', pct.toFixed(2));
  }

  // ---- toolbar buttons --------------------------------------------------
  function centerZoom(factor) {
    zoomAt(leftVp.clientWidth / 2, leftVp.clientHeight / 2, factor);
  }
  bindClick('.ic-zoom-in', function () { centerZoom(1.25); });
  bindClick('.ic-zoom-out', function () { centerZoom(1 / 1.25); });
  bindClick('.ic-fit', fitToViewport);
  bindClick('.ic-actual', actualSize);

  // ---- keyboard ---------------------------------------------------------
  stage.addEventListener('keydown', function (e) {
    var step = 40;
    switch (e.key) {
      case '+': case '=': centerZoom(1.25); break;
      case '-': case '_': centerZoom(1 / 1.25); break;
      case '0': fitToViewport(); break;
      case 'ArrowLeft':  tx += step; apply(); break;
      case 'ArrowRight': tx -= step; apply(); break;
      case 'ArrowUp':    ty += step; apply(); break;
      case 'ArrowDown':  ty -= step; apply(); break;
      default: return;
    }
    e.preventDefault();
  });

  window.addEventListener('resize', function () {
    // keep the current zoom; just make sure something is shown
    apply();
  });

  // ---- init once both images report their natural size ------------------
  function ready(img, cb) {
    if (img.complete && img.naturalWidth) {
      cb();
    } else {
      img.addEventListener('load', cb);
      img.addEventListener('error', cb);
    }
  }
  var loaded = 0;
  function onImg() {
    loaded++;
    if (loaded < 2) {
      return;
    }
    refW = leftImg.naturalWidth || rightImg.naturalWidth || leftImg.width || 1;
    refH = leftImg.naturalHeight || rightImg.naturalHeight || leftImg.height || 1;
    // normalise the right image onto the reference grid so a shared
    // transform shows the same region even if pixel dimensions differ
    leftImg.style.width = refW + 'px';
    leftImg.style.height = 'auto';
    rightImg.style.width = refW + 'px';
    rightImg.style.height = 'auto';
    setMode(app.getAttribute('data-mode'));
    fitToViewport();
  }
  ready(leftImg, onImg);
  ready(rightImg, onImg);

  // ---- helpers ----------------------------------------------------------
  function bindClick(sel, fn) {
    var el = app.querySelector(sel);
    if (el) {
      el.addEventListener('click', fn);
    }
  }

  // ---- save / delete a comparison via the web service -------------------
  function wireSaveDelete() {
    var wsUrl = app.getAttribute('data-ws');
    var token = app.getAttribute('data-token');
    var statusEl = app.querySelector('.ic-status');
    var saveBtn = app.querySelector('.ic-save');
    var removeBtn = app.querySelector('.ic-remove');

    function setStatus(msg, ok) {
      if (!statusEl) {
        return;
      }
      statusEl.textContent = msg;
      statusEl.className = 'ic-status ' + (ok ? 'ic-ok' : 'ic-error');
    }

    function call(method, data, onOk) {
      var body = new FormData();
      body.append('method', method);
      body.append('pwg_token', token);
      for (var k in data) {
        if (Object.prototype.hasOwnProperty.call(data, k)) {
          body.append(k, data[k]);
        }
      }
      fetch(wsUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json && json.stat === 'ok') {
            onOk(json.result || {});
          } else {
            setStatus((json && json.message) || 'Error', false);
          }
        })
        .catch(function () { setStatus('Network error', false); });
    }

    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var label = window.prompt(saveBtn.getAttribute('data-prompt') || 'Optional label for this comparison:', '');
        if (label === null) {
          return; // cancelled
        }
        call('image_comparison.savePair', {
          left: app.getAttribute('data-left'),
          right: app.getAttribute('data-right'),
          label: label
        }, function (result) {
          setStatus(result.message || 'Saved.', true);
          saveBtn.style.display = 'none';
        });
      });
    }

    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        call('image_comparison.deletePair', { pair_id: removeBtn.getAttribute('data-pair') }, function (result) {
          setStatus(result.message || 'Removed.', true);
          removeBtn.style.display = 'none';
        });
      });
    }
  }
})();
