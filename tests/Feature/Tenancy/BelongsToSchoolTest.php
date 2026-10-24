<?php

declare(strict_types=1);

use App\Exceptions\CrossSchoolWriteException;
use App\Exceptions\MissingSchoolContextException;
use App\Models\School;
use App\Models\Subject;
use App\Support\CurrentSchool;

beforeEach(function (): void {
    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();
    Subject::factory()->for($this->schoolA)->create(['code' => 'MATH']);
    Subject::factory()->for($this->schoolB)->create(['code' => 'MATH']);
    $this->context = app(CurrentSchool::class);
});

it('only returns rows of the current school', function (): void {
    $this->context->set($this->schoolA);

    expect(Subject::query()->pluck('school_id')->all())->toBe([$this->schoolA->id]);
});

it('fails closed when no school is set', function (): void {
    Subject::query()->count();
})->throws(MissingSchoolContextException::class);

it('does not find another school\'s record by id', function (): void {
    $other = Subject::query()->withoutGlobalScopes()->where('school_id', $this->schoolB->id)->sole();
    $this->context->set($this->schoolA);

    expect(Subject::query()->find($other->id))->toBeNull();
});

it('fills school_id from the current school on create', function (): void {
    $this->context->set($this->schoolA);

    $subject = Subject::query()->create(['name' => 'Art', 'code' => 'ART']);

    expect($subject->school_id)->toBe($this->schoolA->id);
});

it('refuses to create a record for another school', function (): void {
    $this->context->set($this->schoolA);

    Subject::query()->create(['school_id' => $this->schoolB->id, 'name' => 'Art', 'code' => 'ART']);
})->throws(CrossSchoolWriteException::class);

it('refuses to move a record to another school', function (): void {
    $this->context->set($this->schoolA);
    $subject = Subject::query()->firstOrFail();

    $subject->update(['school_id' => $this->schoolB->id]);
})->throws(CrossSchoolWriteException::class);

it('refuses to create without a school context or explicit school', function (): void {
    Subject::query()->create(['name' => 'Art', 'code' => 'ART']);
})->throws(MissingSchoolContextException::class);

it('restores the previous context after run()', function (): void {
    $this->context->set($this->schoolA);

    $inside = $this->context->run($this->schoolB, fn (): int => $this->context->id());

    expect($inside)->toBe($this->schoolB->id)
        ->and($this->context->id())->toBe($this->schoolA->id);
});
