<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Classes\SyncTeachingAssignments;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\Locale;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Teachers' assignments, students, guardians and enrolments for a demo school.
 *
 * Deterministic (seeded by the school's slug) and without Faker, which is a dev dependency
 * and not installed on the demo server that resets nightly. Students are bulk-inserted:
 * hundreds of rows through Eloquent would make every reset and every DemoSeedTest slow.
 */
class DemoRosterSeeder extends Seeder
{
    private const NAMES = [
        'en' => [
            'female' => ['Olivia', 'Amelia', 'Isla', 'Ava', 'Mia', 'Ivy', 'Lily', 'Isabella', 'Sophia', 'Grace', 'Freya', 'Ella', 'Emily', 'Chloe', 'Ruby', 'Harper', 'Zara', 'Aisha', 'Priya', 'Mei', 'Sofia', 'Hannah', 'Nora', 'Leah'],
            'male' => ['Oliver', 'George', 'Noah', 'Arthur', 'Leo', 'Oscar', 'Harry', 'Jack', 'Charlie', 'Jacob', 'Thomas', 'Henry', 'James', 'Ethan', 'Lucas', 'Samuel', 'Daniel', 'Adam', 'Omar', 'Ravi', 'Kenji', 'Mateo', 'Felix', 'Max'],
            'last' => ['Smith', 'Jones', 'Taylor', 'Brown', 'Williams', 'Wilson', 'Johnson', 'Davies', 'Patel', 'Wright', 'Robinson', 'Thompson', 'Evans', 'Walker', 'White', 'Roberts', 'Green', 'Hall', 'Wood', 'Jackson', 'Clarke', 'Khan', 'Lewis', 'Harris', 'Martin', 'Cooper', 'King', 'Lee', 'Baker', 'Hughes', 'Nguyen', 'Okafor', 'Rossi', 'Novak', 'Kowalski', 'Garcia', 'Murphy', "O'Brien", 'Campbell', 'Mensah'],
        ],
        'de' => [
            'female' => ['Emma', 'Mia', 'Hannah', 'Emilia', 'Sofia', 'Lina', 'Mila', 'Ella', 'Lea', 'Clara', 'Marie', 'Lena', 'Leonie', 'Amelie', 'Luisa', 'Johanna', 'Ida', 'Frieda', 'Greta', 'Paula'],
            'male' => ['Noah', 'Leon', 'Paul', 'Matteo', 'Ben', 'Elias', 'Felix', 'Finn', 'Henry', 'Luis', 'Jonas', 'Emil', 'Theo', 'Anton', 'Lukas', 'Maximilian', 'Jakob', 'David', 'Moritz', 'Karl'],
            'last' => ['Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann', 'Schäfer', 'Koch', 'Bauer', 'Richter', 'Klein', 'Wolf', 'Schröder', 'Neumann', 'Schwarz', 'Zimmermann', 'Braun', 'Krüger', 'Hofmann', 'Hartmann', 'Lange', 'Schmitt', 'Werner', 'Krause', 'Meier', 'Lehmann', 'Yılmaz', 'Kaya', 'Nowak', 'Popescu'],
        ],
    ];

    private const STREETS = ['Hauptstraße', 'Bahnhofstraße', 'Schillerstraße', 'Goethestraße', 'Gartenweg', 'Mainkai', 'Lindenallee', 'Bergstraße', 'Schweizer Straße', 'Eschenheimer Landstraße'];

    private Randomizer $random;

    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly SyncTeachingAssignments $syncAssignments,
    ) {}

    /**
     * @param  list<User>  $teachers  the first one is the "lead" demo teacher
     * @param  array{user: User, children: array<string, string>}|null  $demoParent  children: first name => class
     */
    public function seedSchool(School $school, string $admissionPrefix, int $perClass, array $teachers, string $leadSubjectCode, ?array $demoParent = null): void
    {
        $this->random = new Randomizer(new Mt19937(crc32($school->slug)));

        $this->currentSchool->run($school, function (School $school) use ($admissionPrefix, $perClass, $teachers, $leadSubjectCode, $demoParent): void {
            $year = AcademicYear::query()->current()->firstOrFail();
            $classes = SchoolClass::query()->where('academic_year_id', $year->id)->orderBy('name')->orderBy('section')->get()->values();

            $this->assignTeachers($classes->all(), $teachers, $leadSubjectCode);
            $this->seedStudents($school, $year, $classes->all(), $admissionPrefix, $perClass, $demoParent);
        });
    }

    /**
     * Round-robin, plus the lead teacher teaching their subject in all Grade 7 and 8 classes
     * and being class teacher of the first of them, so the demo teacher login has classes.
     *
     * @param  array<int, SchoolClass>  $classes
     * @param  list<User>  $teachers
     */
    private function assignTeachers(array $classes, array $teachers, string $leadSubjectCode): void
    {
        $subjects = Subject::query()->orderBy('code')->get()->values();
        $lead = $teachers[0];
        $leadClasses = array_values(array_filter($classes, fn (SchoolClass $c): bool => in_array($c->name, ['Grade 7', 'Grade 8'], true)));

        foreach ($classes as $i => $class) {
            $class->update(['class_teacher_id' => ($leadClasses[0] ?? null)?->is($class) ? $lead->id : $teachers[$i % count($teachers)]->id]);

            $this->syncAssignments->handle($class, $subjects->map(fn (Subject $subject, int $j): array => [
                'subject_id' => $subject->id,
                'teacher_id' => $subject->code === $leadSubjectCode && in_array($class, $leadClasses, true)
                    ? $lead->id
                    : $teachers[($i + $j) % count($teachers)]->id,
            ])->all());
        }
    }

    /**
     * @param  array<int, SchoolClass>  $classes
     * @param  array{user: User, children: array<string, string>}|null  $demoParent
     */
    private function seedStudents(School $school, AcademicYear $year, array $classes, string $prefix, int $perClass, ?array $demoParent): void
    {
        $names = self::NAMES[$school->locale === Locale::German ? 'de' : 'en'];

        // One slot per seat in every class, filled family by family in random order.
        $slots = [];
        foreach ($classes as $class) {
            $slots = [...$slots, ...array_fill(0, $perClass, $class)];
        }
        $slots = $this->random->shuffleArray($slots);

        $families = [];

        if ($demoParent !== null) {
            $children = [];
            foreach ($demoParent['children'] as $firstName => $className) {
                $index = array_key_first(array_filter($slots, fn (SchoolClass $c): bool => $c->displayName() === $className))
                    ?? throw new LogicException("The demo school has no class {$className}.");
                $children[] = ['first_name' => $firstName, 'gender' => Gender::Female, 'class' => $slots[$index]];
                unset($slots[$index]);
            }
            $families[] = [
                'last_name' => Str::afterLast($demoParent['user']->name, ' '),
                'guardians' => [[
                    'name' => $demoParent['user']->name,
                    'email' => $demoParent['user']->email,
                    'relationship' => GuardianRelationship::Mother,
                    'user_id' => $demoParent['user']->id,
                ]],
                'children' => $children,
            ];
            $slots = array_values($slots);
        }

        while ($slots !== []) {
            $lastName = $this->pick($names['last']);
            $size = min(count($slots), $this->weighted([1 => 70, 2 => 25, 3 => 5]));
            $familyNo = count($families) + 1;

            $guardians = [$this->guardian($this->pick($names['female']), $lastName, GuardianRelationship::Mother, $familyNo)];
            if ($this->random->getInt(1, 100) <= 80) {
                $guardians[] = $this->guardian($this->pick($names['male']), $lastName, GuardianRelationship::Father, $familyNo);
            }

            $children = [];
            for ($k = 0; $k < $size; $k++) {
                $gender = $this->random->getInt(1, 2) === 1 ? Gender::Female : Gender::Male;
                $children[] = [
                    'first_name' => $this->pick($names[$gender === Gender::Female ? 'female' : 'male']),
                    'gender' => $gender,
                    'class' => array_pop($slots) ?? throw new LogicException('Ran out of class places.'),
                ];
            }

            $families[] = ['last_name' => $lastName, 'guardians' => $guardians, 'children' => $children];
        }

        $this->insert($school, $year, $families, $prefix);
    }

    /**
     * @param  list<array{last_name: string, guardians: array<int, array{name: string, email: string, relationship: GuardianRelationship, user_id?: int}>, children: list<array{first_name: string, gender: Gender, class: SchoolClass}>}>  $families
     */
    private function insert(School $school, AcademicYear $year, array $families, string $prefix): void
    {
        $now = now();
        $students = $guardians = $links = $enrollments = [];
        $number = 0;

        foreach ($families as $family) {
            $address = $this->random->getInt(1, 120).' '.$this->pick(self::STREETS).', 60'.$this->random->getInt(306, 599).' Frankfurt am Main';

            foreach ($family['guardians'] as $guardian) {
                $guardians[] = [
                    'school_id' => $school->id,
                    'user_id' => $guardian['user_id'] ?? null,
                    'name' => $guardian['name'],
                    'email' => $guardian['email'],
                    'phone' => '+49 1'.$this->random->getInt(51, 79).' '.$this->random->getInt(1000000, 9999999),
                    'relationship' => $guardian['relationship']->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($family['children'] as $child) {
                $admissionNo = sprintf('%s-%04d', $prefix, ++$number);
                $grade = (int) preg_replace('/\D/', '', $child['class']->name);

                $students[] = [
                    'school_id' => $school->id,
                    'admission_no' => $admissionNo,
                    'first_name' => $child['first_name'],
                    'last_name' => $family['last_name'],
                    'date_of_birth' => sprintf('%d-%02d-%02d', 2021 - $grade - $this->random->getInt(0, 1), $this->random->getInt(1, 12), $this->random->getInt(1, 28)),
                    'gender' => $child['gender']->value,
                    'address' => $address,
                    'status' => StudentStatus::Active->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                foreach ($family['guardians'] as $index => $guardian) {
                    $links[] = ['admission_no' => $admissionNo, 'email' => $guardian['email'], 'is_primary' => $index === 0];
                }

                $enrollments[] = [
                    'admission_no' => $admissionNo,
                    'class' => $child['class'],
                    'sort' => mb_strtolower($family['last_name'].' '.$child['first_name']),
                ];
            }
        }

        foreach (array_chunk($students, 200) as $chunk) {
            DB::table('students')->insert($chunk);
        }
        foreach (array_chunk($guardians, 200) as $chunk) {
            DB::table('guardians')->insert($chunk);
        }

        $studentIds = DB::table('students')->where('school_id', $school->id)->pluck('id', 'admission_no');
        $guardianIds = DB::table('guardians')->where('school_id', $school->id)->pluck('id', 'email');

        foreach (array_chunk($links, 500) as $chunk) {
            DB::table('guardian_student')->insert(array_map(fn (array $link): array => [
                'guardian_id' => $guardianIds[$link['email']],
                'student_id' => $studentIds[$link['admission_no']],
                'is_primary' => $link['is_primary'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        // Roll numbers follow the alphabet within each class.
        usort($enrollments, fn (array $a, array $b): int => [$a['class']->id, $a['sort']] <=> [$b['class']->id, $b['sort']]);
        $roll = [];
        $rows = array_map(function (array $e) use ($school, $year, $studentIds, $now, &$roll): array {
            $roll[$e['class']->id] = ($roll[$e['class']->id] ?? 0) + 1;

            return [
                'school_id' => $school->id,
                'student_id' => $studentIds[$e['admission_no']],
                'class_id' => $e['class']->id,
                'academic_year_id' => $year->id,
                'roll_no' => $roll[$e['class']->id],
                'enrolled_on' => $year->starts_on->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $enrollments);

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('enrollments')->insert($chunk);
        }
    }

    /**
     * @return array{name: string, email: string, relationship: GuardianRelationship}
     */
    private function guardian(string $firstName, string $lastName, GuardianRelationship $relationship, int $familyNo): array
    {
        return [
            'name' => "{$firstName} {$lastName}",
            // example.com is reserved for documentation, so no demo mail can reach a real person.
            'email' => Str::slug("{$firstName} {$lastName}", '.').".{$familyNo}@example.com",
            'relationship' => $relationship,
        ];
    }

    /**
     * @param  non-empty-list<string>  $values
     */
    private function pick(array $values): string
    {
        return $values[$this->random->getInt(0, count($values) - 1)];
    }

    /**
     * @param  array<int, int>  $weights  value => weight
     */
    private function weighted(array $weights): int
    {
        $roll = $this->random->getInt(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights) ?? 1;
    }
}
