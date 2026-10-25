<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Family\ListNotifications;
use App\Actions\Family\MarkNotificationsRead;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The current school's notifications of the signed-in user (parents receive them: absences,
 * report cards, invoices, payments, reminders).
 */
class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(#[CurrentUser] User $user, ListNotifications $notifications): Response
    {
        return Inertia::render('Notifications/Index', [
            'notifications' => NotificationResource::collection($notifications->handle($user)->cursorPaginate(self::PER_PAGE)),
        ]);
    }

    public function read(string $notification, #[CurrentUser] User $user, MarkNotificationsRead $action): RedirectResponse
    {
        $action->handle($user, $notification);

        return back();
    }

    public function readAll(#[CurrentUser] User $user, MarkNotificationsRead $action): RedirectResponse
    {
        $action->handle($user);

        return back()->with('status', __('All notifications marked as read.'));
    }
}
