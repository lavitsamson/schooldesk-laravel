<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\ReportCards\ApproveReportCard;
use App\Actions\ReportCards\BuildClassReportCards;
use App\Actions\ReportCards\GenerateClassReportCards;
use App\Actions\ReportCards\ListReportCardClasses;
use App\Actions\ReportCards\PublishReportCards;
use App\Actions\ReportCards\RenderReportCardPdf;
use App\Actions\ReportCards\ReopenReportCard;
use App\Actions\ReportCards\SaveReportCardSnapshot;
use App\Enums\ReportCardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportCards\ClassReportCardsRequest;
use App\Models\AcademicYear;
use App\Models\AiCommentDraft;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\CurrentSchool;
use App\Support\TermChoice;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Report cards: generated per class as a queued batch, written and approved per student by
 * the class teacher (or a school admin), then published to parents (ADR 0007).
 */
class ReportCardController extends Controller
{
    /**
     * Signed PDF links on the page stay valid this long.
     */
    private const PDF_LINK_MINUTES = 30;

    public function index(Request $request, #[CurrentUser] User $user, CurrentSchool $currentSchool, ListReportCardClasses $action): Response
    {
        Gate::authorize('viewAny', ReportCard::class);

        $year = AcademicYear::query()->current()->with('terms')->firstOrFail();
        $term = TermChoice::pick($year, $request->query('term'));
        $school = $currentSchool->get();

        return Inertia::render('ReportCards/Index', [
            'year' => $year->name,
            'terms' => TermChoice::options($year),
            'term' => ['id' => $term->id, 'name' => $term->name],
            'classes' => $action->handle($user, $term),
            'aiDrafts' => [
                'enabled' => $school->aiCommentDraftsEnabled(),
                'canChange' => $user->can('update', $school),
            ],
        ]);
    }

    public function class(ClassReportCardsRequest $request, SchoolClass $schoolClass, BuildClassReportCards $action): Response
    {
        $term = $request->term();

        return Inertia::render('ReportCards/Class', [
            'schoolClass' => ['id' => $schoolClass->id, 'name' => $schoolClass->displayName()],
            'terms' => TermChoice::options($schoolClass->academicYear),
            'term' => ['id' => $term->id, 'name' => $term->name],
            // students and batch; the page polls them (partial reload) while a batch runs.
            ...$action->handle($schoolClass, $term),
        ]);
    }

    public function generate(ClassReportCardsRequest $request, SchoolClass $schoolClass, #[CurrentUser] User $user, GenerateClassReportCards $action): RedirectResponse
    {
        $term = $request->term();
        $batch = $action->handle($user, $schoolClass, $term);

        return to_route('report-cards.class', ['schoolClass' => $schoolClass, 'term' => $term->id])
            ->with('status', "Generating {$batch->total} report cards.");
    }

    public function publishClass(ClassReportCardsRequest $request, SchoolClass $schoolClass, PublishReportCards $action): RedirectResponse
    {
        $term = $request->term();
        $published = $action->handle(ReportCard::query()
            ->where('class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->where('status', ReportCardStatus::Approved)
            ->get());

        return back()->with('status', $published === 1 ? '1 report card published.' : "{$published} report cards published.");
    }

    public function show(ReportCard $reportCard, #[CurrentUser] User $user): Response
    {
        Gate::authorize('view', $reportCard);

        $reportCard->load(['approver:id,name', 'schoolClass']);

        return Inertia::render('ReportCards/Show', [
            'schoolClass' => ['id' => $reportCard->class_id, 'name' => $reportCard->schoolClass->displayName()],
            'card' => [
                'id' => $reportCard->id,
                'status' => $reportCard->status->value,
                'comment' => $reportCard->teacher_comment,
                'comment_source' => $reportCard->comment_source?->value,
                'approved_by' => $reportCard->approver?->name,
                'approved_at' => $reportCard->approved_at?->toIso8601String(),
                'published_at' => $reportCard->published_at?->toIso8601String(),
                'pdf_url' => URL::temporarySignedRoute('report-cards.pdf', now()->addMinutes(self::PDF_LINK_MINUTES), ['reportCard' => $reportCard]),
            ],
            'report' => $reportCard->data,
            'siblings' => $this->siblings($reportCard),
            'draft' => fn (): ?array => $this->latestDraft($reportCard),
            'ai' => [
                'enabled' => $user->can('draftComment', $reportCard),
            ],
            'commentMaxLength' => (int) config('schooldesk.report_cards.comment_max_length'),
        ]);
    }

    /**
     * Recalculates a draft from the current marks and attendance.
     */
    public function refresh(ReportCard $reportCard, SaveReportCardSnapshot $action): RedirectResponse
    {
        Gate::authorize('update', $reportCard);

        if (! $reportCard->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Reopen the report card to recalculate it.']);
        }

        $reportCard->load(['student', 'term', 'schoolClass']);
        $action->handle($reportCard->student, $reportCard->term, $reportCard->schoolClass);

        return back()->with('status', 'Recalculated from the current marks.');
    }

    public function approve(ReportCard $reportCard, #[CurrentUser] User $user, ApproveReportCard $action): RedirectResponse
    {
        Gate::authorize('update', $reportCard);
        $action->handle($reportCard, $user);

        return back()->with('status', 'Report card approved.');
    }

    public function reopen(ReportCard $reportCard, ReopenReportCard $action): RedirectResponse
    {
        Gate::authorize('update', $reportCard);
        $action->handle($reportCard);

        return back()->with('status', 'Report card reopened.');
    }

    public function publish(ReportCard $reportCard, PublishReportCards $action): RedirectResponse
    {
        Gate::authorize('update', $reportCard);

        if ($reportCard->status !== ReportCardStatus::Approved) {
            throw ValidationException::withMessages(['status' => $reportCard->isDraft()
                ? 'Approve the report card before publishing it.'
                : 'This report card has already been published.']);
        }

        $action->handle(collect([$reportCard]));

        return back()->with('status', 'Report card published.');
    }

    /**
     * The stored PDF of an approved card; for a draft, a preview rendered on the fly. Signed
     * links only (from the report card page), and authorised again here.
     */
    public function pdf(ReportCard $reportCard, RenderReportCardPdf $render): HttpResponse
    {
        Gate::authorize('view', $reportCard);

        $name = Str::slug('report-card '.$reportCard->data['student']['first_name'].' '.$reportCard->data['student']['last_name'].' '.$reportCard->data['term']['name']).'.pdf';
        $headers = ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'];
        $disk = Storage::disk(ReportCard::DISK);

        if ($reportCard->pdf_path !== null && $disk->exists($reportCard->pdf_path)) {
            return $disk->download($reportCard->pdf_path, $name, $headers);
        }

        return response($render->handle($reportCard), 200, [
            ...$headers,
            'Content-Disposition' => 'inline; filename="draft-'.$name.'"',
        ]);
    }

    /**
     * The previous and next report card of the class in roll order, to write one after the other.
     *
     * @return array{previous: int|null, next: int|null}
     */
    private function siblings(ReportCard $reportCard): array
    {
        $ids = ReportCard::query()
            ->where('report_cards.class_id', $reportCard->class_id)
            ->where('report_cards.term_id', $reportCard->term_id)
            ->join('students', 'students.id', '=', 'report_cards.student_id')
            ->leftJoin('enrollments', fn ($join) => $join
                ->on('enrollments.student_id', '=', 'report_cards.student_id')
                ->on('enrollments.class_id', '=', 'report_cards.class_id'))
            ->orderByRaw('enrollments.roll_no is null')
            ->orderBy('enrollments.roll_no')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->pluck('report_cards.id')
            ->unique()
            ->values();

        $index = $ids->search($reportCard->id);

        return [
            'previous' => $index === false || $index === 0 ? null : $ids->get($index - 1),
            'next' => $index === false ? null : $ids->get($index + 1),
        ];
    }

    /**
     * The newest AI draft not taken over yet, shown beside the comment editor.
     *
     * @return array{id: int, text: string, created_at: string}|null
     */
    private function latestDraft(ReportCard $reportCard): ?array
    {
        $draft = $reportCard->aiDrafts()->where('accepted', false)->latest('id')->first();

        return $draft instanceof AiCommentDraft
            ? ['id' => $draft->id, 'text' => $draft->draft_text, 'created_at' => $draft->created_at->toIso8601String()]
            : null;
    }
}
