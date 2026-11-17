# Presence (Kanboard plugin)

See which project members are **online**, **away** or **offline**, with agent users badged as bots.
Privacy-first: each user can appear offline, an admin can switch it off instance-wide, and no
activity history is kept.

## What it looks like

Every project view (board, list, overview, and so on) gets a compact avatar strip next to the project
header, with one small avatar per active project member, direct or through a group. Each avatar has a
coloured status dot in its lower-right corner: green for online, amber for away, grey for offline.
Offline avatars are also faded. Your own avatar has a thin outline, and agent users carry a small
dark `BOT` tag under their avatar. Hover an avatar to see the name and current state (screen readers
announce the same label); both refresh with every poll. Members are listed
online first, then away, then offline, and alphabetically within each group.

Presence is global, not per project. A lit avatar means the person is active somewhere in Kanboard;
the strip never reveals which project or page they are looking at.

## Install

Download `Presence-1.0.1.zip` from the releases page, or install it from ModMenu, and unzip it into
`plugins/Presence/` (the zip's single top-level folder is `Presence`). The `presence` table is
created on first load. Requires Kanboard `>=1.2.47` and PHP `>=8.4`, and works on SQLite, MySQL and
Postgres.

## States

| State | Humans | Agents |
|---|---|---|
| **online** | a visible tab with mouse or keyboard input in the last 30 min sent a heartbeat in the last 2 min | an authenticated JSON-RPC call in the last 5 min |
| **away** | any authenticated web request in the last 15 min, including an idle tab's heartbeat | (agents are never away) |
| **offline** | anything older, or the user chose "Appear offline" | anything older |

An open tab left alone shows **away** after 30 min with no mouse or keyboard input, and after 2 h it
stops polling altogether, so the user goes **offline** and their PHP session can expire as usual. The
next click or key press resumes the heartbeat immediately. The thresholds are fixed in v1.

## Privacy

- **What is stored:** one row per user, holding `user_id`, `last_seen`, `last_beat` and `source`
  (`web` or `api`). No page, project, IP address or user-agent is recorded, and there is no history:
  each new activity overwrites the row.
- **Appear offline:** every user can turn it on from the avatar menu (one click) or from the
  **Presence** page in their own profile. While it is on, nothing is recorded for that user, the
  heartbeat stops, and teammates always see them as offline.
- **Admin switch:** Settings > Application > Presence ("Show who is online in projects"). With it
  unticked, nothing is recorded for anyone and no strip or heartbeat is rendered. It is on by default.
- Other users never see timestamps, only the three states.

## Agents

Presence recommends [Agents](https://github.com/carmelosantana/kanboard-agents) `>=0.3.2`. When Agents is
installed, members on the agent roster are flagged `is_bot`, get the `BOT` badge, and light up as
online when they make an authenticated JSON-RPC call with their personal token (calls with the
application token, the `jsonrpc` user, belong to no user and record nothing). Without Agents, human
presence works unchanged and nobody is flagged as a bot.

## For theme authors

Presence renders stock markup that themes can restyle, or hide and replace, without overriding any
template.

**DOM:**

- `.presence-strip` — the strip container, with `data-project-id`.
- `.presence-plate` — one per member, with:
  - `data-user-id` — the Kanboard user id
  - `data-presence` — `online`, `away` or `offline`
  - `data-bot` — `1` for an agent, otherwise `0`
  - `data-self` — `1` for the viewer, otherwise `0`
- `.presence-bot` — the `BOT` tag inside an agent's plate.
- The status dot's ring colour reads the `--presence-ring` custom property (default `#fff`).

**Event:** after the first heartbeat and after every poll on a project page, Presence dispatches
`presence:update` on `document`:

```js
detail = {
  project_id,                                   // int
  members: [{ user_id, name, avatar_html, state, is_bot, is_self }]
}
```

`state` is `online`, `away` or `offline`, `is_bot` and `is_self` are booleans, and members arrive
sorted online → away → offline, then by name. A listener that lights your own plates:

```js
document.addEventListener('presence:update', function (e) {
  e.detail.members.forEach(function (m) {
    var el = document.querySelector('.my-plate[data-user-id="' + m.user_id + '"]');
    if (el) { el.dataset.presence = m.state; el.dataset.bot = m.is_bot ? '1' : '0'; }
  });
});
```

## Performance

- One `presence` row per user, updated in place. No cron and no cleanup job.
- Web activity is recorded at most once every 30 s per session; a timestamp in the PHP session means
  most requests never touch the database. API activity is recorded at most once a minute per user,
  through a conditional `UPDATE`. Heartbeat writes are throttled at 30 s.
- The heartbeat is one POST per visible tab every 60 s, and it returns the lineup in the same
  response. It pauses while the tab is hidden and beats again when the tab becomes visible. After 30 min
  with no mouse or keyboard input it slows to every 5 min and marks those beats idle (activity only, no
  heartbeat); after 2 h it stops until the next input. It also stops for good on a 401/403 response
  or when the admin switch is off.
- Each lineup is a fixed handful of batched queries, none per member.

## Development

```bash
# from kanboard-plugins/
./testing/run-plugin-tests.sh Presence        # PHPUnit
node --test 'Presence/Test/js/*.test.mjs'     # heartbeat JS (Node built-in test runner)
```

## Licence

MIT. See [LICENSE](LICENSE).
