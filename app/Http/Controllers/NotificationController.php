<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * The full "Notifikasi" page reached from the bell's footer link.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('thesis/notifications/index', [
            'notifications' => NotificationResource::collection(
                $request->user()->notifications()->limit(50)->get()
            ),
        ]);
    }

    /**
     * Opening a notification marks it read, which is what the bell's badge
     * counts down from.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->ownedNotification($request, $notification)->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /**
     * Notifications are addressed to one person; scoping the lookup to the
     * signed-in user means an id from elsewhere simply does not exist.
     */
    private function ownedNotification(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }
}
