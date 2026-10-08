// Presence heartbeat: one POST per visible tab per minute; backs off when idle; pauses when hidden.
// Config comes from #presence-mount data-* (CSP forbids inline script). Themes listen for 'presence:update'.
(function (window, document) {
  'use strict';
  var BEAT = 60000, IDLE_BEAT = 300000, IDLE_AFTER = 1800000;

  function nextDelay(s) {
    if (!s.visible) return null;
    return s.idleMs > IDLE_AFTER ? IDLE_BEAT : BEAT;
  }

  function applyLineup(root, data) {
    var byId = {};
    (data.members || []).forEach(function (m) { byId[String(m.user_id)] = m; });
    var plates = root.querySelectorAll('.presence-plate');
    for (var i = 0; i < plates.length; i++) {
      var m = byId[plates[i].dataset.userId];
      if (!m) continue;
      plates[i].dataset.presence = m.state;
      plates[i].dataset.bot = m.is_bot ? '1' : '0';
    }
  }

  // The controller reads project_id from the query string only, so it rides on the URL, not the body.
  function beatUrl(base, projectId, origin) {
    var u = new URL(base, origin);
    if (projectId) u.searchParams.set('project_id', projectId);
    return u.toString();
  }

  function start() {
    var mount = document.getElementById('presence-mount');
    if (!mount) return;
    var strip = document.querySelector('.presence-strip');
    // layout:bottom renders without $project, so the mount's id is normally empty; the strip carries it.
    var projectId = mount.dataset.projectId || (strip && strip.dataset.projectId) || '';
    var url = beatUrl(mount.dataset.beatUrl, projectId, window.location.href);
    var lastInput = Date.now(), timer = null;

    function schedule() {
      clearTimeout(timer);
      var d = nextDelay({ visible: document.visibilityState === 'visible', idleMs: Date.now() - lastInput });
      if (d !== null) timer = setTimeout(beat, d);
    }

    function beat() {
      var body = new FormData();
      body.append('csrf_token', mount.dataset.csrf);
      fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (data && data.enabled && data.project_id) {
            if (strip) applyLineup(strip, data);
            document.dispatchEvent(new CustomEvent('presence:update', { detail: { project_id: data.project_id, members: data.members } }));
          }
        })
        .catch(function () {})
        .then(schedule);
    }

    function onInput() {
      var wasIdle = Date.now() - lastInput > IDLE_AFTER;
      lastInput = Date.now();
      if (wasIdle) schedule();
    }

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') beat(); else clearTimeout(timer);
    });
    document.addEventListener('pointerdown', onInput, { passive: true });
    document.addEventListener('keydown', onInput);
    beat();
  }

  window.KBPresence = { nextDelay: nextDelay, applyLineup: applyLineup, beatUrl: beatUrl, start: start };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})(window, document);
