# ADR 0005: Attendance as one record per student and day, with claimed absence notifications

- **Status:** Accepted
- **Date:** 2026-10-24
- **Deciders:** Lavit Samson

## Context

Every morning a teacher marks attendance for a class of about 30 students. Most are present, so
this has to be fast. When a student is absent, the primary guardian is told by email (and in the
parent portal once they have an account), but no more than once per student per day.

There are a few complications:

- Teachers make mistakes and fix them. They save the grid again, sometimes a few times.
- Students change class during the year (ADR 0003). Old attendance has to stay with the class it
  was taken in.
- The schools are in Europe, but the server runs in UTC. Just after midnight in Frankfurt it is
  already "tomorrow" there.
- A job that fails is retried, and a parent should never get the same email twice.

## Decision

1. **One row per student and day.** `attendance_records` has a unique key on
   `(student_id, date)` and stores the `class_id` the attendance was taken in. Saving again
   updates the day's rows instead of adding new ones. History stays with the old class after a
   student moves, and the monthly report reads a class's rows by `class_id`, using the
   `(school_id, class_id, date)` index. Classes with attendance history cannot be deleted. The
   foreign key restricts it, and `DeleteClass` explains why.

2. **The whole grid is saved at once, in one transaction.** Students without a record start as
   present in the UI, so a teacher only clicks on the absent ones. The Form Request checks with
   one query that every student is enrolled in the class on that day. That rejects students who
   have left, students who have not joined yet, and ids from other schools.

3. **"Own classes" means class teacher or subject teacher.** `AttendancePolicy` lets a teacher
   take attendance and see reports for classes they are class teacher of or teach a subject in
   (`SchoolClass::taughtBy()`). School admins can do this for every class. The class comes from
   school-scoped route binding, so another school's class returns 404 before the policy runs.

4. **Notifications are delayed, then claimed.** `RecordAttendance` queues
   `SendAbsenceNotification` (after commit, with a 10-minute delay set in
   `ABSENCE_NOTIFICATION_DELAY_MINUTES`) only when a student *becomes* absent and the guardian
   has not been notified yet. The job locks the record (`SELECT … FOR UPDATE`) and sends only
   if the student is still absent and `guardian_notified_at` is empty. It sets that column
   before it sends. This means:
   - a mark corrected within the delay is never sent;
   - saving again, switching absent → present → absent, duplicate jobs and retries cannot send
     a second email;
   - if the mail transport fails after the claim, that guardian gets no email for that day.
     This is on purpose: at most once is better than duplicates for this kind of message.

   Late and excused never notify anyone. Guardians with a portal account get mail and a database
   notification. Guardians without one get mail only, as an on-demand notification. The
   notification uses only the student's first name.

5. **Dates follow the school's timezone.** `School::today()` is today in the school's timezone,
   returned as a plain date. Attendance can be taken for any day of the class's academic year up
   to today, never in advance. Weekends are allowed (trips, Saturday school), but the
   previous/next day links skip them.

6. **The attendance rate counts present and late as attended.** For each student it is a
   percentage of the days recorded *for that student*, so joining mid-month does not lower it.
   The report's columns are the days on which attendance was actually taken, so holidays do
   not show up as empty columns.

## Consequences

- One unique key handles it all: no duplicates per day, and a natural upsert.
- Absence emails arrive a few minutes late. A short `ABSENCE_NOTIFICATION_DELAY_MINUTES` brings
  them closer to real time, at the cost of the correction window.
- In rare cases a failed mail transport means one guardian gets no email for that day. Failed
  jobs show up in Horizon.
- If two teachers create the same day's first records at exactly the same moment, the unique key
  makes the second save fail, and that teacher saves again. We accepted this rather than use
  table locks.
- Attendance retention (deleting old notes and details after a period set per school) belongs to
  the GDPR milestone. The table has been designed so it can be pruned by date.

## Alternatives considered

- **A "session" row per class and day, with child rows per student.** Easy to tell whether a
  class's attendance has been taken. But a student who moves class mid-day causes conflicts, and
  a second table adds nothing the per-student rows don't already give. "Taken" is worked out
  from the count instead.
- **Sending immediately, and deduplicating with a cache key.** Immediate emails cannot be taken
  back after a wrong click, and a cache key can be evicted. A column on the record is
  permanent, can be audited, and is updated in the same row lock.
- **Notifying from a model observer.** That hides a side effect inside every save, including in
  seeders and imports. The action makes the side effect explicit.
