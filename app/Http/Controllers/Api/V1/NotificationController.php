<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Family\ListNotifications;
use App\Actions\Family\MarkNotificationsRead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListRequest;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Notifications
 *
 * What the school told the parent: absences, published report cards, invoices, payments
 * and reminders. `subject` names the record (student, report_card or invoice).
 */
class NotificationController extends Controller
{
    /**
     * List notifications
     *
     * Newest first, read and unread.
     *
     * @response {"data": [{"id": "9b2f6c1e-5d1a-4a53-9a0e-3c8f2b7d1e44", "type": "student-absent", "title": "Pia was absent on 12 October 2026.", "subject": {"type": "student", "id": 12, "student_id": 12}, "details": {"student_id": 12, "first_name": "Pia", "class": "Grade 7 A", "date": "2026-10-12"}, "read_at": null, "created_at": "2026-10-12T08:15:00+00:00"}], "links": {"first": null, "last": null, "prev": null, "next": null}, "meta": {"path": "https://schooldesk.test/api/v1/notifications", "per_page": 25, "next_cursor": null, "prev_cursor": null}}
     */
    public function index(ListRequest $request, #[CurrentUser] User $user, ListNotifications $notifications): AnonymousResourceCollection
    {
        return NotificationResource::collection($notifications->handle($user)->cursorPaginate($request->limit()));
    }

    /**
     * Mark as read
     *
     * @urlParam notification string required The notification's id. Example: 9b2f6c1e-5d1a-4a53-9a0e-3c8f2b7d1e44
     *
     * @response {"data": {"id": "9b2f6c1e-5d1a-4a53-9a0e-3c8f2b7d1e44", "type": "student-absent", "title": "Pia was absent on 12 October 2026.", "subject": {"type": "student", "id": 12, "student_id": 12}, "details": {"student_id": 12, "first_name": "Pia", "class": "Grade 7 A", "date": "2026-10-12"}, "read_at": "2026-10-12T17:02:00+00:00", "created_at": "2026-10-12T08:15:00+00:00"}}
     */
    public function read(string $notification, #[CurrentUser] User $user, MarkNotificationsRead $action): NotificationResource
    {
        return new NotificationResource($action->handle($user, $notification));
    }
}
