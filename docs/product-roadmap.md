# ProgressOS Product Roadmap Notes

This file captures product and data-model ideas that should be revisited after the current UI/UX polish and activity timeline work.

## Near-Term Product Improvements

- Report snapshots: persist weekly and monthly report outputs so users can compare historical reviews without recalculating everything live.
- Project workflow: add clearer task lifecycle fields such as `started_at`, `completed_at`, and `blocked_reason` where they improve reporting.
- Practice/Labs module: add a separate product area for Sudoku, drills, exercises, and learning practice sessions.
- Import/export backup: allow full user data export and restore for local-first confidence.
- Reminder system: lightweight daily review and weekly review reminders.

## Practice/Labs Direction

Use a new navigation group such as `Practice` or `Labs` rather than mixing games and drills into Work.

Potential data model:

- `practice_sessions`
- `practice_items`
- `practice_results`

Initial use cases:

- Sudoku sessions
- Language drills
- Programming quiz/practice logs
- Reading or book exercises

## API/Integration Direction

Before building Raycast, CLI, WhatsApp, or other connectors, stabilize:

- API documentation
- Token-based auth strategy
- Quick-capture endpoint contract
- Activity timeline endpoint
- Export endpoints

## Current Foundation

The app already has:

- Auditable core models via `audit_logs`
- Recent activity API/dashboard integration
- Custom Vue UI shell with command palette
- REST-backed frontend architecture
