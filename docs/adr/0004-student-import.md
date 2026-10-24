# ADR 0004: Student import as queued chunks with a per-row outcome log

- **Status:** Accepted
- **Date:** 2026-10-24
- **Deciders:** Lavit Samson

## Context

At the start of the year a school brings its roster in from a spreadsheet: several hundred to a
few thousand rows, typed by hand, often saved by German Excel (semicolons, Windows-1252,
`17.05.2014` dates, admission numbers with leading zeros). Some rows will always be wrong.

The requirements: valid rows import even if others fail; the admin sees progress and gets a
report of the failed rows; uploading the same (or a corrected) file again is safe; and a large
file must not tie up a web request.

## Decision

1. **Queued chunks on a dedicated `imports` queue.** The upload is stored on the private disk
   and Laravel Excel reads it in chunks of 200 rows, one job per chunk, followed by a
   completion step. Horizon runs a separate supervisor for `imports`, so a big import never
   delays invitation emails or notifications on `default`. Each chunk runs in a transaction and
   is retried as a whole (3 tries), so a chunk is either fully applied or not at all.

2. **The heading row is checked in the Form Request.** A file without the required columns,
   or one that cannot be read at all, is rejected straight away with one message, instead of
   being queued and failing on every row.

3. **Each row is validated on its own** (`ValidateStudentRow`), then checked against the
   database and the rest of the file (`ImportStudentRows`): duplicate admission numbers within
   the file, numbers of deleted students (never reused, see ADR 0003), and moving inactive
   students into a class. Cells are read as text (leading zeros survive); dates are accepted
   as ISO, German or Excel date cells.

4. **`admission_no` is the key, matched case-insensitively.** A row for an existing student
   updates them; otherwise it creates one. Re-uploading a file therefore only updates. Empty
   optional cells and missing optional columns never clear stored data (e.g. a guardian's
   phone or relationship). Guardians are matched by email first, so siblings share them.

5. **Every row's outcome is stored in `import_rows`** (created / updated / failed, with the
   original values and errors for failed rows only). This one table drives the progress
   counts, the duplicate check across chunks, the failures shown on the progress page, and the
   error report: a CSV of the failed rows in the template format, plus a row number and an
   errors column, which can be fixed and uploaded again.

6. **Data minimisation.** The uploaded file is deleted as soon as the import finishes or
   fails. Batches, their rows and error reports are pruned after 30 days
   (`IMPORT_KEEP_DAYS`, `model:prune` daily).

7. **The export uses the same columns** (`StudentSheet`), so "export selected → edit in
   Excel → import" is a round trip.

## Alternatives considered

- **Synchronous import in the request.** Simple, but a 3,000-row file with guardians takes
  longer than a request should, and there is no progress.
- **All-or-nothing import.** Easier to reason about, but one typo would block a whole roster.
  The spec asks for valid rows to import, and the error report makes the rest easy to fix.
- **Counting outcomes on the batch row only.** Cheaper, but the duplicate check across chunks
  and the error report both need per-row data anyway.
- **Bus batches (`Bus::batch`) instead of a job chain.** Chunks could run in parallel, but two
  chunks could then race on the same admission number or shared guardian. In order is fast
  enough for school-sized files.

## Consequences

- Imports need a queue worker (Horizon) outside tests; in tests and the E2E job the sync queue
  runs the whole import inside the upload request.
- The Redis `retry_after` (330 s) must stay above the import job timeout (300 s), or a slow
  chunk would be started twice.
- Later imports (e.g. grades) can reuse `import_batches` / `import_rows` with a new
  `ImportType`.
