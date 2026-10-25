# ADR 0007: Report cards as approved snapshots, and AI comment drafts with data minimisation

- **Status:** Accepted
- **Date:** 2026-10-25
- **Deciders:** Lavit Samson

## Context

At the end of a term, the class teacher writes a report card for each of about 30 students:
the results per subject (ADR 0006), attendance and a short personal comment. The school then
publishes the cards to parents. Several things make this harder than it looks:

- Marks keep changing while the teacher writes. A teacher corrects a test on Thursday, and the
  report cards started on Monday should pick that up. A card that a parent has already read
  must not change.
- A class has 30 cards. Calculating them, and later rendering 30 PDFs, takes longer than one
  web request should take.
- Writing 30 comments that sound different is the slowest part of the job. A language model can
  suggest a first draft, but the comment goes out under the teacher's name, and under GDPR
  every piece of student data sent to a third party has to be justified.
- Schools differ: some will not want any AI involvement at all.

## Decision

1. **A report card stores the calculated snapshot, and approval freezes it.** `report_cards.data`
   holds the output of `CalculateReportCard` (subjects, grades, overall result, attendance, the
   grade scale used, and when it was calculated). The card has three states:
   - **Draft:** the teacher writes the comment. Regenerating, or "Recalculate" on the card,
     replaces the snapshot with the current marks and keeps the comment.
   - **Approved:** the teacher signs the card off. The snapshot is no longer recalculated,
     the PDF is rendered from it and stored, and `approved_by`/`approved_at` record who did it.
     An approved card can be **reopened** (back to draft, PDF deleted) until it is published.
   - **Published:** parents are notified and can read the card. Published cards are final in
     v1. A correction after publication is a conversation with the school office, not a silent
     edit.

   Approval needs a comment, so an empty card cannot go out by accident.

2. **Generating for a class is a queued Laravel job batch.** "Generate report cards" dispatches
   one `GenerateReportCard` job per student on the `reports` queue, which runs on the
   long-jobs Horizon supervisor next to imports. The batch allows failures, so one broken
   record does not stop the other 29. A school-owned `report_card_batches` row links the job
   batch to the class and term. The class page polls the batch progress through that row, so a
   job batch id is never taken from the request. Only one run per class and term can run at a
   time. Each job sets the school context itself (ADR 0001).

3. **PDFs are rendered from the snapshot with dompdf and kept on the private disk.** The PDF
   (school logo, results table, attendance, comment, approver) is rendered once on approval and
   stored. Drafts get an on-the-fly preview with a "Draft" watermark, which is not stored. Every
   download goes through a temporary signed URL (30 minutes) and is authorised again by
   `ReportCardPolicy`. A valid signature for another school's card still returns 404.

4. **The class teacher and school admins write report cards.** These are the same people who
   see the class results (`GradePolicy::viewResults`). Subject teachers enter marks but do not
   see the drafts, and parents see only published cards (parent portal, milestone 9).

5. **AI drafts are a suggestion beside the comment, never the comment itself.**
   - The feature is **off by default** for every school (`schools.settings.ai_comment_drafts`).
     A school admin turns it on. The settings panel says exactly what would be sent.
   - "Draft comment" stores the suggestion in `ai_comment_drafts`, next to the report card. The
     teacher can take it over into the editor ("Use this draft"), must save it, and can edit it
     like any text. It then becomes the comment with `comment_source = ai_draft_edited`, and
     the draft is marked `accepted`. Nothing reaches parents until the teacher **approves** the
     card, which is the same step for every comment.
   - **Data minimisation.** `BuildCommentDraftRequest` sends only the student's first name, the
     grade per subject, the overall grade, attendance in percent, a trend word since the previous
     term (`improving`/`steady`/`declining`, ±3 points), the term name and the order of the
     school's grades. It does not send the surname, date of birth, address, admission number,
     class, guardians or the teachers' free-text **remarks**. Remarks are left out for two
     reasons: they can contain sensitive personal details ("mother called about the divorce"),
     and they are the obvious channel for prompt injection. A feature test records the outgoing
     request and asserts that none of these fields are in it.
   - **Prompt structure.** The instructions are in the system prompt and the student data is
     JSON in the user turn, labelled as data, not instructions. The model is told to use only
     the data given and to invent nothing.
   - **Cost and abuse limits.** There is a per-school rate limit (100 drafts per hour by
     default) and a `max_tokens` cap per call. Each draft stores its token usage and the model
     that wrote it, and both are logged. The data sent is not stored, only a SHA-256 hash of it.
   - **Provider behind an interface.** `LlmClient` has two implementations.
     `AnthropicLlmClient` calls the Messages API through Laravel's HTTP client, with retries on
     429/5xx and the server-side refusal fallback. `FakeLlmClient` writes a deterministic
     comment from the same JSON. The fake is bound whenever `ANTHROPIC_API_KEY` is empty, so the
     demo, CI and local development work offline, and nothing leaves the server.
   - **Language.** The draft is written in the school's locale (English or German).

## Consequences

- Parents and teachers always read the same numbers: the PDF, the card page and later the
  parent portal all read the one stored snapshot.
- A teacher who approves early and then corrects a mark must reopen the card and recalculate.
  The card page shows when its results were calculated, so this is visible.
- Report cards of the demo seed are published in the middle of the autumn term, so the demo has
  something to show. A real school would publish them after the term has ended.
- The AI draft only knows grades and attendance, so it cannot write about behaviour, effort or
  interests. That is deliberate: those observations are the teacher's. The drafts read as a
  starting point, which is how the UI presents them.
- With the feature off, no code path builds an LLM request (`ReportCardPolicy::draftComment`
  denies it before the action runs).
- Activity logging of approvals and publications comes with the audit milestone (M10).
  `approved_by`, `approved_at` and `published_at` cover the "who and when" until then.
- The UI text and the PDF are English-only until the localisation milestone (M10). The AI draft
  already follows the school's locale.

## Alternatives considered

- **Calculate report cards on the fly, store only the comment.** This is simpler, but then a
  published report card would change whenever a mark or the grade scale is edited. It would
  also break "what the parent saw" in any later dispute.
- **One job per class instead of per student.** This is fewer jobs, but it gives no progress to
  show, and one failure would fail the whole class.
- **Render PDFs in the batch.** Rendering 30 PDFs of drafts that will change is wasted work.
  Rendering on approval means a PDF exists exactly for the cards that are final.
- **Write the AI draft straight into the comment field.** That is one click fewer, but it blurs
  who wrote what. A draft kept beside the editor makes "the teacher chose to use this" an
  explicit action, which `comment_source` records.
- **Send the full snapshot, including assessment names and remarks, for "better" comments.**
  More data would give more specific text, but it would send personal data that the comment
  does not need, and it would open the prompt to injection through remarks. A minimal payload
  is easier to justify to a data protection officer and easier to test.
- **The Anthropic PHP SDK instead of the HTTP client.** The spec asks for the Laravel HTTP
  client: one endpoint, one request shape, and `Http::fake()` in tests without another
  dependency.
