/* ------------------------------------------------------------------ *
 * Image Comparison — viewer behaviour (vanilla JS, no jQuery).
 *
 * Provides synchronised zoom & pan across two images and a draggable
 * reveal divider for the slider mode. Degrades gracefully: without JS
 * the images are still shown by the template/CSS.
 * ------------------------------------------------------------------ */
(function () {
  'use strict';

  function init() {
    var block = document.getElementById('imageComparisonBlock');
    if (!block) { return; }
    var viewer = block.querySelector('.ic-viewer');
    if (!viewer) { return; }

    var mode = viewer.getAttribute('data-mode') || 'side_by_side';
    var sync = viewer.getAttribute('data-sync') === '1';
    if (mode === 'slider') { sync = true; } // overlay only aligns when synced

    var MIN_SCALE = 1;
    var MAX_SCALE = 8;

    var viewports = viewer.querySelectorAll('.ic-viewport');
    var views = [];
    var i;
    for (i = 0; i < viewports.length; i++) {
      var img = viewports[i].querySelector('.ic-img');
      if (img) {
        views.push({ viewport: viewports[i], img: img, scale: 1, tx: 0, ty: 0 });
      }
    }
    if (!views.length) { return; }

    function clamp(v, lo, hi) { return Math.min(hi, Math.max(lo, v)); }

    function clampPan(view) {
      var vw = view.viewport.clientWidth;
      var vh = view.viewport.clientHeight;
      if (view.scale <= 1) { view.tx = 0; view.ty = 0; return; }
      view.tx = clamp(view.tx, vw * (1 - view.scale), 0);
      view.ty = clamp(view.ty, vh * (1 - view.scale), 0);
    }

    function apply(view) {
      view.img.style.transform =
        'translate(' + view.tx + 'px,' + view.ty + 'px) scale(' + view.scale + ')';
    }

    function targets(primary) { return sync ? views : [primary]; }

    function zoomAt(view, px, py, factor) {
      var newScale = clamp(view.scale * factor, MIN_SCALE, MAX_SCALE);
      var k = newScale / view.scale;
      view.tx = px - k * (px - view.tx);
      view.ty = py - k * (py - view.ty);
      view.scale = newScale;
      clampPan(view);
      apply(view);
    }

    function zoomAll(factor) {
      for (var j = 0; j < views.length; j++) {
        var v = views[j];
        zoomAt(v, v.viewport.clientWidth / 2, v.viewport.clientHeight / 2, factor);
      }
    }

    function resetAll() {
      for (var j = 0; j < views.length; j++) {
        views[j].scale = 1; views[j].tx = 0; views[j].ty = 0;
        apply(views[j]);
      }
    }

    // ---- Wheel zoom + pointer pan per viewport ----
    views.forEach(function (view) {
      view.viewport.addEventListener('wheel', function (e) {
        e.preventDefault();
        var rect = view.viewport.getBoundingClientRect();
        var px = e.clientX - rect.left;
        var py = e.clientY - rect.top;
        var factor = e.deltaY < 0 ? 1.15 : 1 / 1.15;
        targets(view).forEach(function (v) { zoomAt(v, px, py, factor); });
      }, { passive: false });

      var dragging = false, lastX = 0, lastY = 0;

      view.viewport.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'mouse' && e.button !== 0) { return; }
        dragging = true;
        lastX = e.clientX; lastY = e.clientY;
        view.viewport.classList.add('is-panning');
        try { view.viewport.setPointerCapture(e.pointerId); } catch (err) {}
      });
      view.viewport.addEventListener('pointermove', function (e) {
        if (!dragging) { return; }
        var dx = e.clientX - lastX;
        var dy = e.clientY - lastY;
        lastX = e.clientX; lastY = e.clientY;
        targets(view).forEach(function (v) { v.tx += dx; v.ty += dy; clampPan(v); apply(v); });
      });
      function endPan(e) {
        dragging = false;
        view.viewport.classList.remove('is-panning');
        try { view.viewport.releasePointerCapture(e.pointerId); } catch (err) {}
      }
      view.viewport.addEventListener('pointerup', endPan);
      view.viewport.addEventListener('pointercancel', endPan);
      view.viewport.addEventListener('dblclick', function (e) {
        var rect = view.viewport.getBoundingClientRect();
        var px = e.clientX - rect.left, py = e.clientY - rect.top;
        var factor = view.scale > 1 ? (1 / view.scale) : 2; // toggle zoom
        targets(view).forEach(function (v) { zoomAt(v, px, py, factor); });
      });
    });

    // ---- Toolbar zoom buttons ----
    var btnIn = viewer.querySelector('.ic-zoom-in');
    var btnOut = viewer.querySelector('.ic-zoom-out');
    var btnReset = viewer.querySelector('.ic-zoom-reset');
    if (btnIn) { btnIn.addEventListener('click', function () { zoomAll(1.3); }); }
    if (btnOut) { btnOut.addEventListener('click', function () { zoomAll(1 / 1.3); }); }
    if (btnReset) { btnReset.addEventListener('click', resetAll); }

    // ---- Sync toggle (disabled in slider mode, where sync is mandatory) ----
    var syncInput = viewer.querySelector('.ic-sync-input');
    if (syncInput) {
      if (mode === 'slider') {
        syncInput.checked = true;
        syncInput.disabled = true;
      } else {
        syncInput.addEventListener('change', function () {
          sync = syncInput.checked;
          if (sync && views.length === 2) {
            // align the second image to the first when re-enabling sync
            views[1].scale = views[0].scale;
            views[1].tx = views[0].tx;
            views[1].ty = views[0].ty;
            clampPan(views[1]);
            apply(views[1]);
          }
        });
      }
    }

    // ---- Slider reveal divider ----
    if (mode === 'slider') {
      var stage = viewer.querySelector('.ic-stage');
      var divider = viewer.querySelector('.ic-divider');
      var rightViewport = viewer.querySelector('.ic-pane-right .ic-viewport');
      if (stage && divider && rightViewport) {
        var pos = 50;
        var setPos = function (pct) {
          pos = clamp(pct, 0, 100);
          divider.style.left = pos + '%';
          rightViewport.style.clipPath = 'inset(0 0 0 ' + pos + '%)';
          rightViewport.style.webkitClipPath = 'inset(0 0 0 ' + pos + '%)';
          divider.setAttribute('aria-valuenow', Math.round(pos));
        };
        var dragDivider = false;
        var fromEvent = function (e) {
          var rect = stage.getBoundingClientRect();
          if (rect.width <= 0) { return; }
          setPos(((e.clientX - rect.left) / rect.width) * 100);
        };
        divider.addEventListener('pointerdown', function (e) {
          dragDivider = true;
          divider.setPointerCapture(e.pointerId);
          e.preventDefault();
        });
        divider.addEventListener('pointermove', function (e) {
          if (dragDivider) { fromEvent(e); }
        });
        var endDiv = function (e) {
          dragDivider = false;
          try { divider.releasePointerCapture(e.pointerId); } catch (err) {}
        };
        divider.addEventListener('pointerup', endDiv);
        divider.addEventListener('pointercancel', endDiv);
        divider.addEventListener('keydown', function (e) {
          if (e.key === 'ArrowLeft') { setPos(pos - 2); e.preventDefault(); }
          else if (e.key === 'ArrowRight') { setPos(pos + 2); e.preventDefault(); }
        });
        setPos(50);
      }
    }

    // ---- Save comparison (admin only; button present only then) ----
    var saveBtn = viewer.querySelector('.ic-save');
    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var msgTitle = viewer.getAttribute('data-msg-title') || 'Title (optional):';
        var msgSaved = viewer.getAttribute('data-msg-saved') || 'Saved';
        var msgError = viewer.getAttribute('data-msg-error') || 'Error';
        var title = window.prompt(msgTitle, '');
        if (title === null) { return; }

        var method = viewer.getAttribute('data-save-method');
        var base = viewer.getAttribute('data-ws-url');
        var url = base + '&method=' + encodeURIComponent(method);

        var fd = new FormData();
        fd.append('method', method);
        fd.append('left_id', viewer.getAttribute('data-left-id'));
        fd.append('right_id', viewer.getAttribute('data-right-id'));
        fd.append('mode', mode);
        fd.append('title', title);
        fd.append('pwg_token', viewer.getAttribute('data-token'));

        saveBtn.disabled = true;
        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d && d.stat === 'ok') { window.alert(msgSaved); }
            else { window.alert(msgError + (d && d.message ? ': ' + d.message : '')); }
          })
          .catch(function () { window.alert(msgError); })
          .then(function () { saveBtn.disabled = false; });
      });
    }

    // initial transform
    resetAll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
