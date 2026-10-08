import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const src = readFileSync(new URL('../../Assets/js/presence.js', import.meta.url), 'utf8');
const win = {};
new Function('window', 'document', src)(win, { readyState: 'loading', addEventListener() {} });
const P = win.KBPresence;

test('polls every 60s while active and visible', () => {
  assert.equal(P.nextDelay({ visible: true, idleMs: 0 }), 60000);
});
test('backs off to 5 min after 30 min idle', () => {
  assert.equal(P.nextDelay({ visible: true, idleMs: 30 * 60000 + 1 }), 300000);
});
test('pauses while hidden', () => {
  assert.equal(P.nextDelay({ visible: false, idleMs: 0 }), null);
});
test('applyLineup sets data-presence by user id and ignores unknown plates', () => {
  const plates = [
    { dataset: { userId: '2', presence: 'offline' }, setAttribute() {} },
    { dataset: { userId: '9', presence: 'away' }, setAttribute() {} },
  ];
  const root = { querySelectorAll: () => plates };
  P.applyLineup(root, { members: [{ user_id: 2, state: 'online', is_bot: true }] });
  assert.equal(plates[0].dataset.presence, 'online');
  assert.equal(plates[0].dataset.bot, '1');
  assert.equal(plates[1].dataset.presence, 'away');
});

const origin = 'https://kb.example';
test('beatUrl puts project_id in the query when the base already has one', () => {
  const u = new URL(P.beatUrl('/?controller=HeartbeatController&action=beat&plugin=Presence', '7', origin));
  assert.equal(u.searchParams.get('project_id'), '7');
  assert.equal(u.searchParams.get('controller'), 'HeartbeatController');
  assert.equal(u.searchParams.get('plugin'), 'Presence');
});
test('beatUrl puts project_id in the query of a pretty route', () => {
  const u = new URL(P.beatUrl('/presence/beat', '7', origin));
  assert.equal(u.pathname, '/presence/beat');
  assert.equal(u.searchParams.get('project_id'), '7');
});
test('beatUrl leaves project_id off when there is no project', () => {
  const u = new URL(P.beatUrl('/presence/beat', '', origin));
  assert.equal(u.searchParams.has('project_id'), false);
});

test('stops polling after 2 h with no input', () => {
  assert.equal(P.nextDelay({ visible: true, idleMs: 2 * 3600000 }), 300000);
  assert.equal(P.nextDelay({ visible: true, idleMs: 2 * 3600000 + 1 }), null);
});
test('beatUrl flags an idle beat with idle=1 in the query', () => {
  const u = new URL(P.beatUrl('/presence/beat', '7', origin, true));
  assert.equal(u.searchParams.get('idle'), '1');
  assert.equal(u.searchParams.get('project_id'), '7');
  assert.equal(new URL(P.beatUrl('/presence/beat', '7', origin, false)).searchParams.has('idle'), false);
});
test('applyLineup refreshes the plate title and aria-label with name and state', () => {
  const attrs = {};
  const plate = { dataset: { userId: '2' }, title: 'Amy — offline', setAttribute: (k, v) => { attrs[k] = v; } };
  P.applyLineup({ querySelectorAll: () => [plate] }, { members: [{ user_id: 2, name: 'Amy', state: 'away', is_bot: false }] });
  assert.equal(plate.title, 'Amy — away');
  assert.equal(attrs['aria-label'], 'Amy — away');
});

// A live start() against stubbed document, clock, timers and fetch.
function harness(respond) {
  let clock = 1e12, nextId = 1;
  const timers = new Map(), calls = [], listeners = {};
  const mount = { dataset: { beatUrl: '/presence/beat', csrf: 't', projectId: '7' } };
  const doc = {
    readyState: 'loading', visibilityState: 'visible',
    addEventListener: (t, f) => { (listeners[t] ||= []).push(f); },
    getElementById: () => mount, querySelector: () => null, dispatchEvent() {},
  };
  const w = { location: { href: origin + '/board/7' } };
  const fetchStub = (url) => { calls.push(new URL(url)); return Promise.resolve(respond()); };
  const st = (fn, d) => { const id = nextId++; timers.set(id, { fn, d }); return id; };
  const ct = (id) => { timers.delete(id); };
  new Function('window', 'document', 'fetch', 'setTimeout', 'clearTimeout', 'Date', src)(w, doc, fetchStub, st, ct, { now: () => clock });
  const flush = () => new Promise((r) => setImmediate(r));
  return {
    calls, timers, flush,
    start: async () => { w.KBPresence.start(); await flush(); },
    advance: (ms) => { clock += ms; },
    fire: async () => { const [id, t] = [...timers][0]; timers.delete(id); t.fn(); await flush(); },
    emit: async (type) => { (listeners[type] || []).forEach((f) => f({})); await flush(); },
    pending: () => [...timers.values()].map((t) => t.d),
  };
}
const ok = () => ({ ok: true, status: 200, json: () => Promise.resolve({ enabled: true, project_id: 7, members: [] }) });

test('idle beats carry idle=1 at the 5 min cadence, then stop after 2 h, then resume on input', async () => {
  const h = harness(ok);
  await h.start();
  assert.equal(h.calls[0].searchParams.has('idle'), false);
  assert.deepEqual(h.pending(), [60000]);

  h.advance(31 * 60000);
  await h.fire();
  assert.equal(h.calls[1].searchParams.get('idle'), '1');
  assert.deepEqual(h.pending(), [300000]);

  h.advance(90 * 60000); // 2 h 1 min idle
  await h.fire();
  assert.equal(h.calls[2].searchParams.get('idle'), '1');
  assert.deepEqual(h.pending(), [], 'no beat is scheduled after 2 h idle');

  await h.emit('pointerdown');
  assert.equal(h.calls.length, 4, 'input after the stop beats immediately');
  assert.equal(h.calls[3].searchParams.has('idle'), false);
  assert.deepEqual(h.pending(), [60000]);
});

for (const status of [401, 403]) {
  test(`stops polling on HTTP ${status}`, async () => {
    const h = harness(() => ({ ok: false, status, json: () => Promise.reject(new Error('no body')) }));
    await h.start();
    assert.deepEqual(h.pending(), []);
    await h.emit('visibilitychange');
    assert.equal(h.calls.length, 1, 'a stopped heartbeat stays stopped');
  });
}
test('stops polling when the server reports presence disabled', async () => {
  const h = harness(() => ({ ok: true, status: 200, json: () => Promise.resolve({ enabled: false, project_id: 7, members: [] }) }));
  await h.start();
  assert.deepEqual(h.pending(), []);
  await h.emit('pointerdown');
  assert.equal(h.calls.length, 1);
});
test('keeps polling after a transient server error', async () => {
  const h = harness(() => ({ ok: false, status: 500, json: () => Promise.reject(new Error('x')) }));
  await h.start();
  assert.deepEqual(h.pending(), [60000]);
});
