# Stop logging every submitted form to em_logger (2026-11-25)

## Problem

`redcap_every_page_before_render()` called `$this->emLog(PAGE, $_POST, $_GET)`.
That hook runs on every page of every project with Summarize enabled, and `emLog` always ships (it is not gated by the debug flags).
So every data-entry and survey save sent every submitted field value to em_logger, which is PHI in most projects.

## Fix

The `emLog` line is removed. The hook still sets `deleteAction` when a form is deleted; nothing else changed.

## How it was reproduced and verified (local, REDCap 17.5.9, PID 212)

A Playwright script signs in as an ordinary data-entry user, creates a record, and types a unique marker into a text field on `qsu_intake_questionnaire`.
It then greps `summarize.json` in em_logger's log directory for the marker.

| Code | Marker in `summarize.json` |
| --- | --- |
| `master` before the fix | present |
| this branch | absent |

The summary field (`html_summary`) was still written for the test record after the fix.
The script is `emlogger-phi-review/e2e/form-save.js` in the REDCap EM workspace.
