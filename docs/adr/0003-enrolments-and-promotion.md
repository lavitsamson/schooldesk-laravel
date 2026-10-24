# ADR 0003: Enrolments per academic year, and promotion as new enrolments

- **Status:** Accepted
- **Date:** 2026-10-24
- **Deciders:** Lavit Samson

## Context

A student is in a different class every year. Attendance, grades and report cards (later
milestones) must keep pointing at the class the student was in *at the time*, even after they
move up. The common shortcut is a class column on the student row. It is overwritten every
September, and last year's report cards lose their class.

At the end of the year a school moves hundreds of students on at once: most go up one grade,
some repeat the year, and the final grade leaves.

## Decision

1. **`enrollments` is the link between a student and a class**: one row per student per
   academic year (unique on `student_id, academic_year_id`), with `roll_no`, `enrolled_on` and
   `left_on`. Classes belong to an academic year and never move to another one. A student's
   "current class" is their enrolment in the school's current year.

2. **Moving a student within the year updates that year's enrolment** (new class, next free roll
   number). A student who leaves keeps the enrolment, closed with `left_on`. Nothing is
   deleted, so the history stays intact.

3. **Promotion creates next year's enrolments. It changes nothing in this year.**
   - The admin first adds next year (optionally copying this year's classes).
   - The promotion screen suggests a target class for each class ("Grade 7 A" → "Grade 8 A").
     It suggests "graduate" for the highest grade. The admin reviews every target and can
     exclude individual students (repeating the year).
   - One transaction enrols the students into next year's classes from its first day. Roll
     numbers follow the alphabet. Graduates get status `graduated`, and this year's enrolment
     closes on its last day.
   - Students who are already enrolled next year are skipped, so running it twice is
     harmless.
   - Switching the school to the new year ("Make current") is a separate step. Promotion can
     then happen in June while the school keeps working in the current year until September.

4. **Admission numbers are never reused.** The unique index includes soft-deleted students.
   A reused number would merge two children's records in exports and in the import (where
   `admission_no` is the key).

## Alternatives considered

- **`class_id` on `students`, plus a history table.** Simpler reads for "the current class".
  But every write has to keep two places in sync, and the history is the part that matters
  for report cards.
- **Promotion that moves the existing enrolment row into the new year.** This loses the old
  year's class, which is the original problem again.
- **Fully automatic promotion (no review step).** Schools always have exceptions (repeaters,
  students who change section), and undoing a wrong bulk move is much harder than a preview.

## Consequences

- Queries for "students of class X" go through `enrollments`. The student list joins the
  current-year enrolment for display, filtering and sorting.
- Deleting a class or an academic year with enrolments is refused (application check and a
  restricting foreign key).
- Attendance and grades (milestones 5–6) reference the enrolment's class and year, not the
  student's current class.
