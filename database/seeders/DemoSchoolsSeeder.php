<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Members\GrantRole;
use App\Actions\Setup\ApplyGradeScalePreset;
use App\Actions\Setup\CompleteSetup;
use App\Actions\Setup\SaveAcademicYear;
use App\Actions\Setup\SyncClasses;
use App\Actions\Setup\SyncSubjects;
use App\Enums\GradeScalePreset;
use App\Enums\Locale;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Database\Seeder;

/**
 * The two demo schools, set up through the same actions as the setup wizard.
 * Students, attendance and grades are added by later milestones.
 */
class DemoSchoolsSeeder extends Seeder
{
    public const PASSWORD = 'DemoPass123!';

    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly GrantRole $grantRole,
    ) {}

    public function run(): void
    {
        $riverside = $this->school([
            'name' => 'Riverside International School',
            'slug' => 'riverside',
            'locale' => Locale::English,
        ], GradeScalePreset::LetterAToF, terms: ['Autumn term', 'Spring term', 'Summer term'], subjects: [
            'ENG' => 'English', 'MATH' => 'Mathematics', 'SCI' => 'Science', 'HIST' => 'History',
            'GEO' => 'Geography', 'GER' => 'German', 'FRE' => 'French', 'ART' => 'Art',
            'MUS' => 'Music', 'PE' => 'Physical Education',
        ]);

        $gymnasium = $this->school([
            'name' => 'Gymnasium am Main',
            'slug' => 'gymnasium-am-main',
            'locale' => Locale::German,
        ], GradeScalePreset::German1To6, terms: ['1. Trimester', '2. Trimester', '3. Trimester'], subjects: [
            'DE' => 'Deutsch', 'MA' => 'Mathematik', 'EN' => 'Englisch', 'BIO' => 'Biologie',
            'PH' => 'Physik', 'CH' => 'Chemie', 'GE' => 'Geschichte', 'EK' => 'Erdkunde',
            'KU' => 'Kunst', 'SP' => 'Sport',
        ]);

        $this->member($riverside, 'admin@riverside.demo', 'Rachel Admin', Role::SchoolAdmin);
        $this->member($riverside, 'teacher@riverside.demo', 'Tom Teacher', Role::Teacher);
        $this->member($riverside, 'parent@riverside.demo', 'Paula Parent', Role::Parent);
        $this->member($gymnasium, 'admin@gymnasium.demo', 'Anna Schulleitung', Role::SchoolAdmin);
    }

    /**
     * @param  array{name: string, slug: string, locale: Locale}  $attributes
     * @param  list<string>  $terms
     * @param  array<string, string>  $subjects
     */
    private function school(array $attributes, GradeScalePreset $preset, array $terms, array $subjects): School
    {
        $school = School::query()->create([
            ...$attributes,
            'country' => 'DE',
            'timezone' => 'Europe/Berlin',
            'currency' => 'EUR',
        ]);

        $this->currentSchool->run($school, function (School $school) use ($preset, $terms, $subjects): void {
            app(SaveAcademicYear::class)->handle([
                'name' => '2026/27',
                'starts_on' => '2026-08-17',
                'ends_on' => '2027-07-16',
                'terms' => [
                    ['name' => $terms[0], 'starts_on' => '2026-08-17', 'ends_on' => '2026-12-18'],
                    ['name' => $terms[1], 'starts_on' => '2027-01-04', 'ends_on' => '2027-03-26'],
                    ['name' => $terms[2], 'starts_on' => '2027-04-12', 'ends_on' => '2027-07-16'],
                ],
            ]);

            $classes = [];
            foreach (range(5, 10) as $grade) {
                foreach (['A', 'B'] as $section) {
                    $classes[] = ['name' => "Grade {$grade}", 'section' => $section];
                }
            }
            app(SyncClasses::class)->handle($classes);

            app(SyncSubjects::class)->handle(array_map(
                fn (string $code, string $name): array => ['code' => $code, 'name' => $name],
                array_keys($subjects),
                $subjects,
            ));

            app(ApplyGradeScalePreset::class)->handle($preset);
            app(CompleteSetup::class)->handle($school);
        });

        return $school;
    }

    private function member(School $school, string $email, string $name, Role $role): void
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $school->memberships()->create(['user_id' => $user->id, 'status' => MembershipStatus::Active]);
        $this->grantRole->handle($user, $school, $role);
    }
}
