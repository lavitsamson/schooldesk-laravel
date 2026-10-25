<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ReportCards\ApproveReportCard;
use App\Actions\ReportCards\SaveReportCardSnapshot;
use App\Actions\ReportCards\UpdateAiCommentDrafts;
use App\Enums\CommentSource;
use App\Enums\ReportCardStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Report cards for one class in the current term, at every stage: a few published (Pia's
 * among them, for the parent portal), a few approved, some drafts with a comment and the rest
 * without one, so the class page shows the whole workflow. AI comment drafts are turned on for
 * the school (written by FakeLlmClient unless an API key is set).
 *
 * Published without notifications: seeded data should not send mail. ADR 0007 notes that the
 * demo publishes in the middle of the term.
 */
class DemoReportCardsSeeder extends Seeder
{
    /**
     * The first word is replaced by the student's first name; the texts avoid pronouns, so
     * they fit any student.
     */
    private const COMMENTS = [
        'Name has had an excellent start to the year, with thoughtful questions in class and careful, well-organised written work. Reading widely will help to build on these strong results.',
        'Name has worked steadily this term, and the results in Mathematics show real progress. More regular revision before tests would help to reach the next level.',
        'Name is a conscientious member of the class, and the project work this term was particularly creative. Sharing ideas more often in class discussions is the next step.',
        'Name has shown good effort, especially in practical work, which was very careful. Taking a little more time to check written answers would pay off.',
        'Name has settled in well and is making steady progress across the subjects. Regular practice of the basics will build confidence in Mathematics.',
    ];

    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly SaveReportCardSnapshot $saveSnapshot,
        private readonly ApproveReportCard $approve,
        private readonly UpdateAiCommentDrafts $aiDrafts,
    ) {}

    public function seedSchool(School $school, string $className, User $classTeacher, int $published = 2, int $approved = 2): void
    {
        $this->aiDrafts->handle($school, true);

        $this->currentSchool->run($school, function (School $school) use ($className, $classTeacher, $published, $approved): void {
            $year = AcademicYear::query()->current()->with('terms')->firstOrFail();
            /** @var Term $term */
            $term = $year->defaultTerm($school->today());
            $class = $year->classes()->get()->first(fn (SchoolClass $class): bool => $class->displayName() === $className)
                ?? throw new RuntimeException("No class {$className} in the current year.");

            $enrollments = Enrollment::query()
                ->where('class_id', $class->id)
                ->activeBetween($term->starts_on, $term->ends_on)
                ->with('student')
                ->get()
                // The parent demo login's child first, so her card is among the published ones.
                ->sortBy(fn (Enrollment $e): array => [$e->student->first_name === 'Pia' ? 0 : 1, $e->roll_no ?? PHP_INT_MAX])
                ->values();

            foreach ($enrollments as $index => $enrollment) {
                $card = $this->saveSnapshot->handle($enrollment->student, $term, $class);
                $comment = self::COMMENTS[$index] ?? null;

                if ($comment === null) {
                    continue;
                }

                $card->update([
                    'teacher_comment' => $enrollment->student->first_name.substr($comment, strlen('Name')),
                    'comment_source' => CommentSource::Manual,
                ]);

                if ($index < $published + $approved) {
                    $this->approve->handle($card, $classTeacher);
                }

                if ($index < $published) {
                    $card->update(['status' => ReportCardStatus::Published, 'published_at' => now()]);
                }
            }
        });
    }
}
