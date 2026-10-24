# ADR 0006: Assessments, exact grade calculation and autosaved mark entry

- **Status:** Accepted
- **Date:** 2026-10-24
- **Deciders:** Lavit Samson

## Context

Teachers set tests, exams and assignments for the subjects they teach in a class, and enter
marks for about 30 students at a time. At the end of a term, each student gets a result per
subject, an overall result and a grade, which go on the report card (milestone 7).

There are a few complications:

- Assessments have different maximum marks (10, 20, 100) and some count more than others (an
  exam more than a homework project).
- Not every student has marks for every assessment. Some were absent, and some assessments have
  not happened yet.
- Grade scales differ: letters A–F at Riverside, the German 1–6 scale (where 1 is best) at the
  Gymnasium. Schools may adjust the bands.
- A result of 89.995 % must be an A every time, not sometimes a B because of float rounding.
  Parents check report cards with a calculator, and they notice.
- Students sometimes change class during a term (ADR 0003).
- Teachers type marks quickly and expect a spreadsheet, not a form with a save button.

## Decision

1. **An assessment belongs to a class, a subject and a term.** It has `max_marks` (a whole
   number) and a relative `weight` (`decimal(5,2)`, default by type: test 1, exam 2,
   assignment 0.5). Marks are stored per student in `grades` with a unique key on
   `(assessment_id, student_id)`. `marks` is a `decimal(6,2)` and may be null when there is
   only a remark such as "absent".

2. **Teachers manage only the class-subjects they teach.** `AssessmentPolicy` allows a teacher to
   set assessments and enter marks only for a class-subject they are assigned to in
   `class_subject_teacher`. Being class teacher is not enough, because the maths teacher enters
   the maths marks. School admins can do this for every class-subject. Whole-class results
   (`GradePolicy::viewResults`) are for school admins and the class teacher, who writes the
   report cards. A record of another school is never found, so it returns 404 (ADR 0001).

3. **The calculation is a pure function, separate from loading the data.**
   `ReportCardCalculator` takes plain arrays and has no database access, so unit tests cover it
   directly:
   - **subject %** = Σ(weight × marks / max_marks) / Σ(weight) × 100, over *marked*
     assessments only. Max marks do not act as a hidden weight: 10/10 and 50/100 at equal
     weights give 75 %, not 54.55 %.
   - **Missing marks are left out**, not counted as zero. An absent student is not marked down
     for a test they did not sit, and a planned exam does not lower anyone's result before it
     happens. The number of marked assessments is shown next to each result (`2 of 3 marked`),
     so a result based on little evidence is visible.
   - **overall %** = the plain average of the *rounded* subject percentages, with every subject
     counting equally. Subjects without marks are left out. Using the rounded values means that
     anyone can check the overall result from the report card.
   - **Rounding**: half up to two decimals, which is the precision of the grade bands.

4. **All arithmetic uses exact decimals.** Marks, weights and percentages are strings and are
   calculated with bcmath through a small `Decimal` helper. Decimal columns arrive from the
   database as strings anyway. With floats, `(89.99 + 90) / 2` can round to 89.99, and that turns
   an A into a B. `ext-bcmath` is required in `composer.json`.

5. **Grade bands include both ends and must be contiguous.** A band such as B = 80.00–89.99 %
   includes both 80.00 and 89.99. Each band ends 0.01 below where the next one starts, so every
   two-decimal percentage falls into exactly one band. `GradeBands::problems()` rejects scales
   that have gaps, overlaps, or do not cover 0–100 %. Both presets pass this check, and so must
   any custom scale. The German scale works the same way. Only the labels run the other way, with
   1 as the best grade.

6. **Results are calculated on demand and stored as snapshots later.** The gradebook and the
   results pages calculate from the current marks and the current scale every time. That is cheap,
   because `CalculateReportCard::forClass()` uses a fixed number of queries for a whole class. It
   returns a self-contained snapshot: subjects with their assessments, overall result, attendance
   in the term, the grade scale used, and when it was calculated. In milestone 7, report cards
   store this snapshot. A published report card then does not change when marks are corrected or
   the scale is edited later.

7. **The subjects in a result** are those taught in the student's class, plus any subject the
   student has marks in. Marks from a class the student left during the term still count. On the
   mark sheet, a student who has marks keeps them even after they move to another class.

8. **The mark grid autosaves changed rows as JSON.** The grid sends only the rows that changed,
   800 ms after typing stops, to `PUT /assessments/{id}/grades`. One request runs at a time, and
   changes made while it is running are sent next. The server saves the batch all or nothing. A
   row with neither marks nor a remark is deleted. Invalid marks (above the maximum, not a number,
   more than two decimals) are flagged in the cell and are not sent until they are fixed. If the
   server returns 422, its errors are mapped back to their cells. Network errors are retried. The
   grid accepts a decimal comma, because German keyboards type `12,5`. Enter and ↓ move to the
   next student, ↑ moves to the previous one. This is the one place in the app that uses `fetch`
   instead of an Inertia visit, because a page visit on every keystroke would reset the grid.

## Consequences

- One formula, used in the gradebook, the class results and (next) the report cards, so they
  always agree. It is covered by unit tests on the calculator: weights, missing marks, rounding
  on the boundaries of both scales.
- Results for a term with few marked assessments may look better or worse than they really are.
  The "x of y marked" count is how the teacher sees this.
- Leaving missing marks out means a teacher who wants an absence counted as zero has to enter
  0. This is explicit and visible, and it is not a silent default.
- Calculating on demand is cheap for a class but would not scale to a school-wide ranking. Nothing
  needs that in v1. Report cards will read their stored snapshot instead.
- Classes, subjects and terms with assessments cannot be deleted. The foreign keys restrict it,
  and the actions explain why, as they do for attendance.
- Activity logging of mark changes comes with the audit milestone. `entered_by` and `updated_at`
  on each grade cover the "who changed this last" question until then.

## Alternatives considered

- **Floats with `round()`.** These are simpler, but rounding on the boundaries depends on binary
  representation and on the PHP version's rounding fixes. The grades on a report card are the
  last place where "usually right" is acceptable.
- **Weights as percentages that must add up to 100 per subject.** Teachers add assessments
  during the term, so the weights of a half-finished term would never add up. Relative weights
  work at every point in the term, and they mean the same thing.
- **Counting missing marks as zero.** This is a common default in spreadsheets. It punishes
  absence silently, and it makes every result before the end of term look like a failing grade.
- **Overall result weighted by subject hours.** Schools do not agree on this, and no data model
  for hours exists yet. An equal weight per subject is the transparent default, and it could
  become a school setting later.
- **Saving the whole grid with a button (as in attendance).** Attendance takes a single click
  per absent student. Marks need typing for every student, and losing 30 typed marks to a closed
  tab is the failure teachers remember. Autosave per changed row removes that risk.
