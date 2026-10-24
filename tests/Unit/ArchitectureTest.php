<?php

declare(strict_types=1);

arch('php preset')->preset()->php();

arch('security preset')->preset()->security()->ignoring('assert');

arch('every application file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging helpers are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('controllers are suffixed with Controller')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('form requests extend the framework request')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('models extend eloquent')
    ->expect('App\Models')
    ->classes()
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->ignoring(['App\Models\Scopes', 'App\Models\Concerns']);

arch('actions are final and expose a handle method')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toHaveMethod('handle');

arch('enums are backed enums')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('policies are suffixed with Policy')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');
