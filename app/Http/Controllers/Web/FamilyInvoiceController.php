<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Fees\ListFamilyInvoices;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A parent's invoices for all their children. The parent portal (milestone 9) builds on this.
 */
class FamilyInvoiceController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, ListFamilyInvoices $action): Response
    {
        Gate::authorize('viewFamily', Invoice::class);

        return Inertia::render('Invoices/Family', [
            'invoices' => InvoiceResource::collection($action->handle($user))->resolve(),
        ]);
    }
}
