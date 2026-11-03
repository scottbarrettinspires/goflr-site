<?php
// Server settings. Blocked from direct web access by .htaccess. Keep this repository private.
const GOOGLE_CLIENT_ID = '635313816169-b0ibgp7e6056ohnali4oq6kq42cv9kd4.apps.googleusercontent.com';
const ROSTER_URL = 'PASTE_APPS_SCRIPT_URL_HERE';   // the roster script's /exec address
const ROSTER_KEY = 'bc30edc5724cfdeb8898ab2ce43c6e6b0e88a31373c9e331';                          // must match the key inside the roster script
const RECHECK_SECONDS = 600;                        // how often a signed-in agent is re-checked against the roster
const SESSION_DAYS = 14;
