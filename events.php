<?php
// FLR training & events schedule -- the ONE place events are listed. The Training page (training.php) and the Home
// page's "Next up" card both read from here, and event registrations (evreglib.php) are tied to each event's
// date + start time, so keep those two fields stable once agents may have registered.
//
// To add an event, copy an entry and edit it. Fields:
//   date     YYYY-MM-DD
//   status   '' (normal), 'cancelled' (shown struck through, no registering) or 'holiday' (a greeting card, no times)
//   type     the small tag, e.g. 'Round Table', 'Round Table on Zoom', 'Special Event'
//   title, start / end ('5:00 PM'), where (short, for Home), address (full, for Training), zoom (link), rsvp (an
//   outside RSVP form, if the event has one), desc (the paragraph on the Training page)
// Past events drop off by themselves; old entries can be deleted any time.
date_default_timezone_set('America/New_York');

$FLR_SCHEDULE = [
    [
        'date' => '2026-11-04',
        'status' => 'cancelled',
        'type' => 'Round Table',
        'title' => 'Mortgage Round Table (presenter to be announced)',
        'start' => '11:00 AM',
        'end' => '12:30 PM',
        'where' => 'In person · FLR office, 11923 Oak Trail Way, Port Richey, FL 34668',
        'address' => 'In person · FLR office, 11923 Oak Trail Way, Port Richey, FL 34668',
        'zoom' => '',
        'rsvp' => '',
        'desc' => 'Details coming soon. A mortgage professional will present; the topic and presenter will be announced.',
    ],
    [
        'date' => '2026-11-05',
        'status' => '',
        'type' => 'Round Table on Zoom',
        'title' => 'AI Basics and Free Prompts',
        'start' => '5:00 PM',
        'end' => '6:00 PM',
        'where' => 'Live on Zoom',
        'address' => 'Live on Zoom',
        'zoom' => 'https://us02web.zoom.us/j/83262695403',
        'rsvp' => '',
        'desc' => 'Explore the basics of using AI in your real estate business and leave with free, ready-to-use prompts you can copy right away. Learn how to ask better questions, save time on everyday tasks, and get practical ideas for marketing, follow-up, and client communication.',
    ],
    [
        'date' => '2026-11-11',
        'status' => '',
        'type' => 'Round Table',
        'title' => 'Growing Your Real Estate Business Through the Holidays',
        'start' => '11:00 AM',
        'end' => '12:30 PM',
        'where' => 'In person · FLR office, Port Richey',
        'address' => 'In person · FLR office, 11923 Oak Trail Way, Port Richey, FL 34668',
        'zoom' => '',
        'rsvp' => '',
        'desc' => 'Join fellow real estate agents for a practical roundtable on generating business during the holiday season. We’ll share scripts and objection handlers for helping sellers feel confident about listing during the holidays, plus simple, thoughtful ways to wish your database happy holidays and stay in touch without making every message a sales pitch. Walk away with ideas you can put into action right away.',
    ],
    [
        'date' => '2026-11-12',
        'status' => '',
        'type' => 'Round Table on Zoom',
        'title' => 'Growing Your Real Estate Business Through the Holidays',
        'start' => '5:00 PM',
        'end' => '6:00 PM',
        'where' => 'Live on Zoom',
        'address' => 'Live on Zoom',
        'zoom' => 'https://us02web.zoom.us/j/84306879241',
        'rsvp' => '',
        'desc' => 'Join fellow real estate agents for a practical roundtable on generating business during the holiday season. We’ll share scripts and objection handlers for helping sellers feel confident about listing during the holidays, plus simple, thoughtful ways to wish your database happy holidays and stay in touch without making every message a sales pitch. Walk away with ideas you can put into action right away.',
    ],
    [
        'date' => '2026-11-18',
        'status' => '',
        'type' => 'Round Table',
        'title' => 'Canva User Templates and Navigating Hynri Skills',
        'start' => '11:00 AM',
        'end' => '12:30 PM',
        'where' => 'In person · FLR office, Port Richey',
        'address' => 'In person · FLR office, 11923 Oak Trail Way, Port Richey, FL 34668',
        'zoom' => '',
        'rsvp' => '',
        'desc' => 'Explore how to use Canva templates to create polished, on-brand marketing pieces in minutes, from listing flyers to social media posts. Then walk through the Hynri skills available to you, how to choose the right one for each task, and how to put them to work in your daily business. Bring your laptop and your questions.',
    ],
    [
        'date' => '2026-11-19',
        'status' => '',
        'type' => 'Round Table on Zoom',
        'title' => 'Canva User Templates and Navigating Hynri Skills',
        'start' => '5:00 PM',
        'end' => '6:00 PM',
        'where' => 'Live on Zoom',
        'address' => 'Live on Zoom',
        'zoom' => 'https://us02web.zoom.us/j/87661882400',
        'rsvp' => '',
        'desc' => 'Explore how to use Canva templates to create polished, on-brand marketing pieces in minutes, from listing flyers to social media posts. Then walk through the Hynri skills available to you, how to choose the right one for each task, and how to put them to work in your daily business. Bring your laptop and your questions.',
    ],
    [
        'date' => '2026-11-26',
        'status' => 'holiday',
        'type' => 'Holiday',
        'title' => 'Happy Thanksgiving from Florida Luxury Realty!',
        'start' => '',
        'end' => '',
        'where' => '',
        'address' => '',
        'zoom' => '',
        'rsvp' => '',
        'desc' => 'There are no round table or Zoom sessions this week, so slow down, gather around the table and enjoy the people who matter most. We are so grateful for each of you: your hard work, your care for your clients and the way you make FLR feel like family. Have a wonderful Thanksgiving!',
    ],
    [
        'date' => '2026-12-10',
        'status' => '',
        'type' => 'Special Event',
        'title' => 'Agent Appreciation Dinner',
        'start' => '6:00 PM',
        'end' => '9:00 PM',
        'where' => 'Vesh',
        'address' => 'Vesh · 5811 Richey Dr, Port Richey, FL 34668',
        'zoom' => '',
        'rsvp' => 'https://form.jotform.com/263024926246053',
        'desc' => 'Join us for an evening of food and celebration as we thank our agents for an amazing year. Semi-formal attire; you may bring one guest, and there is no cost to you. Please RSVP by Tuesday, December 1. Coordinated by your office admin Lindsay.',
    ],
    [
        'date' => '2026-12-25',
        'status' => 'holiday',
        'type' => 'Holiday',
        'title' => 'Merry Christmas from Florida Luxury Realty!',
        'start' => '',
        'end' => '',
        'where' => '',
        'address' => '',
        'zoom' => '',
        'rsvp' => '',
        'desc' => 'There are no round table or Zoom sessions this week, so put the phone down, enjoy the lights and make memories with the people you love. Thank you for an amazing year at FLR. Wishing you and your family a merry Christmas and a joyful holiday season!',
    ],
    [
        'date' => '2027-01-01',
        'status' => 'holiday',
        'type' => 'Holiday',
        'title' => 'Happy New Year from Florida Luxury Realty!',
        'start' => '',
        'end' => '',
        'where' => '',
        'address' => '',
        'zoom' => '',
        'rsvp' => '',
        'desc' => 'There are no round table or Zoom sessions this week while we ring in 2027. Thank you for everything you poured into 2026. Rest up, set your goals and get ready, because the best year yet starts now. Happy New Year from all of us at Florida Luxury Realty!',
    ],
];

// Unique key for an event (what registrations are stored against).
function flr_event_key($e) { return $e['date'] . '|' . ($e['start'] !== '' ? $e['start'] : $e['title']); }
// When an event ends (end of day if it has no end time), Eastern.
function flr_event_end($e) {
    $tz = new DateTimeZone('America/New_York');
    $d = DateTime::createFromFormat('Y-m-d g:i A', $e['date'] . ' ' . ($e['end'] !== '' ? $e['end'] : '11:59 PM'), $tz);
    return $d ?: new DateTime($e['date'] . ' 23:59', $tz);
}
function flr_event_past($e) { return flr_event_end($e) < new DateTime('now', new DateTimeZone('America/New_York')); }
// Agents can register for anything that isn't a holiday greeting, cancelled, or over.
function flr_event_open($e) { return $e['status'] === '' && !flr_event_past($e); }
function flr_event_by_key($key) { global $FLR_SCHEDULE; foreach ($FLR_SCHEDULE as $e) { if (flr_event_key($e) === $key) return $e; } return null; }

// Paired sessions: the same training taught twice (Wednesday 11 AM in person, Thursday 5 PM on Zoom) shows as one card
// with both sessions. Events group when they have the same title and fall within 3 days of each other; holidays and
// cancellations always stand alone. Each session keeps its own registration.
function flr_event_groups(array $events) {
    $groups = [];
    foreach ($events as $e) {
        $last = count($groups) - 1;
        if ($e['status'] === '' && $last >= 0 && $groups[$last][0]['status'] === '' && $groups[$last][0]['title'] === $e['title']
            && (strtotime($e['date']) - strtotime($groups[$last][0]['date'])) <= 3 * 86400) { $groups[$last][] = $e; continue; }
        $groups[] = [$e];
    }
    return $groups;
}
// Tag for a card: a pair taught in person and on Zoom says so; otherwise the event's own type.
function flr_group_tag(array $g) {
    if (count($g) < 2) return $g[0]['type'];
    $zoom = count(array_filter($g, function ($e) { return $e['zoom'] !== ''; }));
    return ($zoom && $zoom < count($g)) ? preg_replace('/ on Zoom$/', '', $g[0]['type']) . ' · In person & on Zoom' : $g[0]['type'];
}
// The day number(s) on a card's date tile: "11" or "11–12" (or "30–2" across a month end; the month shown is the first).
function flr_group_days(array $g) {
    $a = (int)date('j', strtotime($g[0]['date'])); $b = (int)date('j', strtotime(end($g)['date']));
    return $a === $b ? (string)$a : $a . '–' . $b;
}

// Real events only (no holidays or cancellations), in date order -- what the Home "Next up" card picks from.
$FLR_EVENTS = array_values(array_filter($FLR_SCHEDULE, function ($e) { return $e['status'] === ''; }));
