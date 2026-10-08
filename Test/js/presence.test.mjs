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
    { dataset: { userId: '2', presence: 'offline' } },
    { dataset: { userId: '9', presence: 'away' } },
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
