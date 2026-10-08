// Presence heartbeat: one POST per visible tab per minute; pauses when hidden. After 30 min with no
// input, beats go every 5 min flagged idle=1 (the user reads away); after 2 h they stop until input.
// Config comes from #presence-mount data-* (CSP forbids inline script). Themes listen for 'presence:update'.
(function (window, document) {
  'use strict';
  var BEAT = 60000, IDLE_BEAT = 300000, IDLE_AFTER = 1800000, STOP_AFTER = 7200000;

  function nextDelay(s) {
    if (!s.visible || s.idleMs > STOP_AFTER) return null;
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
      plates[i].title = m.name + ' — ' + m.state;
      plates[i].setAttribute('aria-label', plates[i].title);
    }
  }

  // The controller reads params from the query string only, so project_id and idle ride on the URL.
  function beatUrl(base, projectId, origin, idle) {
    var u = new URL(base, origin);
    if (projectId) u.searchParams.set('project_id', projectId);
    if (idle) u.searchParams.set('idle', '1');
    return u.toString();
  }

  function start() {
    var mount = document.getElementById('presence-mount');
    if (!mount) return;
    var strip = document.querySelector('.presence-strip');
    // layout:bottom renders without $project, so the mount's id is normally empty; the strip carries it.
    var projectId = mount.dataset.projectId || (strip && strip.dataset.projectId) || '';
    var lastInput = Date.now(), timer = null, stopped = false;

    function idleMs() { return Date.now() - lastInput; }

    function schedule() {
      clearTimeout(timer);
      if (stopped) return;
      var d = nextDelay({ visible: document.visibilityState === 'visible', idleMs: idleMs() });
      if (d !== null) timer = setTimeout(beat, d);
    }

    // Signed out, forbidden or switched off: no point polling again from this page.
    function stop() { stopped = true; clearTimeout(timer); }

    function beat() {
      if (stopped) return;
      var url = beatUrl(mount.dataset.beatUrl, projectId, window.location.href, idleMs() > IDLE_AFTER);
      var body = new FormData();
      body.append('csrf_token', mount.dataset.csrf);
      fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) {
          if (r.status === 401 || r.status === 403) { stop(); return null; }
          return r.ok ? r.json() : null;
        })
        .then(function (data) {
          if (data && data.enabled === false) stop();
          if (data && data.enabled && data.project_id) {
            if (strip) applyLineup(strip, data);
            document.dispatchEvent(new CustomEvent('presence:update', { detail: { project_id: data.project_id, members: data.members } }));
          }
        })
        .catch(function () {})
        .then(schedule);
    }

    function onInput() {
      var idle = idleMs();
      lastInput = Date.now();
      if (idle > STOP_AFTER) beat(); // polling had stopped: come back now
      else if (idle > IDLE_AFTER) schedule();
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
