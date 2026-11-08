# goflr.com — notes for anyone (human or Claude) building this site

FLR's agent and staff back office. PHP + SQLite on Hostinger, deployed automatically from `main`.
Data lives on the server (flrdata/, outside the web root); documents live in Google Drive.

## Build for AI control, not just for clicking
Agents will use Claude Cowork with many custom skills, and an FLR MCP server will let those skills read and
drive this site. Design every feature so it can be done without the web page:
- Keep each action in a library function (leadlib, dlib, msglib, collablib, ohlib, formslib, oauthlib…) that
  takes plain arguments and returns data or an error string; pages and APIs only call it. No logic that lives
  only in page HTML or browser JavaScript.
- Give records stable IDs (lead id, transaction folder id, message id, post id) and return them from actions.
- Prefer JSON endpoints next to pages (`*_api.php`) for anything an assistant would want: list, get, create,
  update, mark done. Same permission checks as the page; act only as the signed-in person.
- Validate on the server with clear, human-readable error messages (an assistant will relay them).
- Record important changes with `flr_audit()` so actions taken by an assistant are traceable.
- Dates as YYYY-MM-DD, times in America/New_York; Florida deadlines roll weekends/holidays to the next business day (dlib).

## Sample data
Preview data is marked SAMPLE- (folders, signature ids), smp (lead ids), created='SAMPLE' (prospecting).
Settings > Sample data clears it all before launch (samplelib.php). Keep new sample data in that same scheme.
