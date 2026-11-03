<?php
// Server settings. Blocked from direct web access by .htaccess. Keep this repository private.
const GOOGLE_CLIENT_ID = '635313816169-b0ibgp7e6056ohnali4oq6kq42cv9kd4.apps.googleusercontent.com';
const ROSTER_URL = 'https://script.google.com/macros/s/AKfycbybrvY_bkrq9c95WT_7HDYVU65mv_UVi1q6ipbMJn8AC3jsAWTTUN65r6Vdz6aR8AK4/exec';
// Temporary fallback: the old roster sheet. Remove once Agent Records sign-in is confirmed working.
const OLD_ROSTER_URL = 'https://script.google.com/macros/s/AKfycbzTydY23rkYJ0sxpK-MPGqxS_FYj0uA2gHdtcUgpFtcE_Q8iXlrqSXeIF8IsNEw8YpV/exec';   // the roster script's /exec address
const ROSTER_KEY = 'bc30edc5724cfdeb8898ab2ce43c6e6b0e88a31373c9e331';                          // must match the key inside the roster script
const RECHECK_SECONDS = 600;                        // how often a signed-in agent is re-checked against the roster
const SESSION_DAYS = 14;
