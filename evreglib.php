<?php
// Event registration: agents (and staff) tap Register on a training event, and everyone can see who's going as a row
// of tiny headshots. Registrations are stored against flr_event_key() (date + start time) from events.php.
// Rendering lives here (er_widget) so the Training page, the Home "Next up" card and ev_api.php all draw it the same way.
require_once __DIR__ . '/events.php';
require_once __DIR__ . '/msglib.php'; // om_my_keys / om_person: who someone is and their directory headshot

function er_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS ev_regs (ev_key TEXT NOT NULL, email TEXT NOT NULL, name TEXT, person_key TEXT, created TEXT, PRIMARY KEY (ev_key, email))');
    return $pdo;
}
function er_me() {
    $email = strtolower(trim($_SESSION['email'] ?? '')); $staff = function_exists('flr_is_broker') && flr_is_broker();
    $keys = om_my_keys($email, $staff);
    return ['email' => $email, 'name' => trim($_SESSION['name'] ?? '') ?: $email, 'key' => $staff ? 'staff:' . $email : ($keys[0] ?? '')];
}
// [ev_key => [rows in sign-up order]] for the given event keys.
function er_for(array $keys) {
    if (!$keys) return [];
    $q = er_db()->prepare('SELECT * FROM ev_regs WHERE ev_key IN (' . implode(',', array_fill(0, count($keys), '?')) . ') ORDER BY created');
    $q->execute(array_values($keys)); $out = [];
    foreach ($q->fetchAll() as $r) $out[$r['ev_key']][] = $r;
    return $out;
}
// Register or un-register me. Returns '' or an error.
function er_set($evKey, $on) {
    $e = flr_event_by_key($evKey); if (!$e) return 'That event is no longer on the schedule.';
    if ($on && !flr_event_open($e)) return 'Registration is closed for this event.';
    $me = er_me(); if ($me['email'] === '') return 'Please sign in again.';
    if ($on) er_db()->prepare('INSERT OR IGNORE INTO ev_regs (ev_key, email, name, person_key, created) VALUES (?,?,?,?,?)')->execute([$evKey, $me['email'], $me['name'], $me['key'], date('c')]);
    else er_db()->prepare('DELETE FROM ev_regs WHERE ev_key = ? AND email = ?')->execute([$evKey, $me['email']]);
    return '';
}

const ER_ZOOM_ICON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="14" height="12" rx="2"/><path d="m22 8-6 4 6 4z"/></svg>';
const ER_PLACE_ICON = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>';

// Each session of a card on its own line -- day, time, place -- with that session's Zoom / Register / who's going.
// $full: true = full address (Training page), false = short place (Home card).
function er_sessions(array $group, array $regs, $full = true) {
    $h = '<div class="ersess">';
    foreach ($group as $e) {
        $place = $full ? $e['address'] : $e['where'];
        $h .= '<div class="ers"><div class="erwhen">' . ($e['zoom'] !== '' ? ER_ZOOM_ICON : ER_PLACE_ICON) . '<span><b>' . htmlspecialchars(date('l, M j', strtotime($e['date']))) . '</b>'
            . htmlspecialchars(($e['start'] !== '' ? ' · ' . $e['start'] . ' – ' . $e['end'] : '') . ($place !== '' ? ' · ' . $place : '')) . '</span></div>'
            . er_widget($e, $regs[flr_event_key($e)] ?? []) . '</div>';
    }
    return $h . '</div>';
}

// The Register button + "who's going" headshots for one event. $regs = that event's rows from er_for().
function er_widget($e, array $regs) {
    if ($e['status'] !== '') return '';
    $key = flr_event_key($e); $open = flr_event_open($e); $me = er_me()['email'];
    $mine = false; $people = [];
    foreach ($regs as $r) {
        $isMe = $r['email'] === $me; if ($isMe) $mine = true;
        $people[] = ['me' => $isMe, 'name' => $isMe ? 'You' : $r['name'], 'p' => om_person($r['person_key'], $r['name'])];
    }
    usort($people, function ($a, $b) { return $b['me'] <=> $a['me']; }); // you first
    $n = count($people);
    $h = '<div class="ereg" data-key="' . htmlspecialchars($key) . '">';
    // Zoom sessions: the join link sits right beside Register, same size.
    if ($e['zoom'] !== '' && !flr_event_past($e)) $h .= '<a class="btn erzoom" target="_blank" rel="noopener" href="' . htmlspecialchars($e['zoom']) . '">' . ER_ZOOM_ICON . 'Join on Zoom</a>';
    if ($open) {
        $h .= $mine ? '<button type="button" class="btn ghost erbtn on" data-on="0" title="Tap to cancel your registration">&#10003; You\'re registered</button>'
                    : '<button type="button" class="btn erbtn" data-on="1">Register</button>';
    }
    if ($n) {
        $h .= '<button type="button" class="erwho" aria-expanded="false"><span class="erstack">';
        foreach (array_slice($people, 0, 5) as $i => $x) {
            $h .= '<span class="erav" style="z-index:' . (10 - $i) . '" title="' . htmlspecialchars($x['name']) . '"><span>' . htmlspecialchars($x['p']['ini']) . '</span>'
                . ($x['p']['photo'] ? '<img src="' . htmlspecialchars($x['p']['photo']) . '" alt="" loading="lazy" onerror="this.remove()">' : '') . '</span>';
        }
        if ($n > 5) $h .= '<span class="erav ermore">+' . ($n - 5) . '</span>';
        $first = array_map(function ($x) { return $x['me'] ? 'You' : explode(' ', $x['name'])[0]; }, array_slice($people, 0, 2));
        $txt = $n === 1 ? $first[0] : ($n === 2 ? $first[0] . ' and ' . $first[1] : $first[0] . ', ' . $first[1] . ' and ' . ($n - 2) . ' other' . ($n - 2 === 1 ? '' : 's'));
        $verb = ($n === 1 && !$mine) ? 'is going' : 'are going';
        $h .= '</span><span class="ertxt">' . htmlspecialchars($txt) . ' ' . $verb . '</span></button><ul class="erlist" hidden>';
        foreach ($people as $x) $h .= '<li><span class="erav sm"><span>' . htmlspecialchars($x['p']['ini']) . '</span>' . ($x['p']['photo'] ? '<img src="' . htmlspecialchars($x['p']['photo']) . '" alt="" loading="lazy" onerror="this.remove()">' : '') . '</span>' . htmlspecialchars($x['name']) . '</li>';
        $h .= '</ul>';
    } elseif ($open) {
        $h .= '<span class="ertxt muted">Be the first to register</span>';
    }
    return $h . '</div>';
}

// Styles + script for the widget; print once per page that shows it.
function er_assets() {
    $csrf = json_encode($_SESSION['csrf'] ?? '');
    return <<<HTML
<style>
.ereg{display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;margin-top:12px;position:relative}
.erbtn,.erzoom{border:1px solid transparent;box-sizing:border-box;cursor:pointer;font:inherit;font-size:.88rem;font-weight:600;padding:6px 14px;line-height:1.4}
.erzoom{display:inline-flex;align-items:center;gap:6px;text-decoration:none}.erzoom svg{flex:0 0 auto}
.ersess{margin-top:10px;border-top:1px solid var(--line,#d7e2e2)}
.ers{padding:10px 0;border-bottom:1px solid var(--line,#d7e2e2)}.ers:last-child{border-bottom:0;padding-bottom:0}
.erwhen{display:flex;gap:8px;align-items:flex-start;font-size:.9rem;color:#455355}.erwhen svg{flex:0 0 auto;margin-top:3px;color:var(--teal,#036f72)}
.ers .ereg{margin-top:8px}
.tile span.range{font-size:1.25rem;letter-spacing:-.02em}.erbtn.on{color:#2e7d32;border:1px solid #2e7d32;background:#eef7ef}
.erwho{display:flex;flex-wrap:wrap;align-items:center;gap:6px 8px;max-width:100%;background:none;border:0;padding:0;font:inherit;color:inherit;cursor:pointer;text-align:left;-webkit-tap-highlight-color:transparent}
.erwho:hover .ertxt{text-decoration:underline}
.erstack{display:flex;padding-left:6px}
.erav{position:relative;width:28px;height:28px;margin-left:-8px;border-radius:50%;overflow:hidden;background:var(--teal-soft,#e6f2f2);color:var(--teal,#036f72);font:700 .62rem/28px system-ui,sans-serif;text-align:center;border:2px solid #fff;box-sizing:content-box;flex:0 0 auto}
.erav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.erav.ermore{background:#5a6a6c;color:#fff}
.erav.sm{margin:0;width:26px;height:26px;line-height:26px;border:0}
.ertxt{font-size:.88rem;color:#2a3638;flex:1 1 150px;min-width:0}.ertxt.muted{color:#5a6a6c}
.erlist{position:absolute;top:100%;left:0;z-index:20;list-style:none;margin:6px 0 0;padding:8px 12px;background:#fff;border:1px solid var(--line,#d7e2e2);border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.12);max-height:260px;overflow:auto;min-width:200px}
.erlist[hidden]{display:none}.erlist li{display:flex;align-items:center;gap:8px;padding:4px 0;font-size:.9rem;color:#2a3638}
</style>
<script>
(function(){var CSRF={$csrf};
document.addEventListener("click",function(e){
  var w=e.target.closest(".erwho");document.querySelectorAll(".erlist:not([hidden])").forEach(function(l){if(!w||l.previousElementSibling!==w)l.hidden=true;});
  if(w){var l=w.nextElementSibling;l.hidden=!l.hidden;w.setAttribute("aria-expanded",l.hidden?"false":"true");return;}
  var b=e.target.closest(".erbtn");if(!b)return;var box=b.closest(".ereg"),on=b.dataset.on==="1";
  if(!on&&!confirm("Cancel your registration for this event?"))return;
  b.disabled=true;
  fetch("ev_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:CSRF,key:box.dataset.key,on:on})})
   .then(function(r){return r.json();}).then(function(j){if(!j.ok){alert(j.msg||"Couldn't save that. Please try again.");b.disabled=false;return;}
     var t=document.createElement("div");t.innerHTML=j.html;document.querySelectorAll('.ereg[data-key="'+CSS.escape(box.dataset.key)+'"]').forEach(function(x){x.replaceWith(t.firstElementChild.cloneNode(true));});})
   .catch(function(){alert("Couldn't save that. Please try again.");b.disabled=false;});});
})();
</script>
HTML;
}
