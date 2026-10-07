<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(20)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'onboarding_id' => $n->data['onboarding_id'] ?? null,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at,
            ]),
        ];
    }

    public function markRead(Request $request, string $id)
    {
        $request->user()->notifications()->where('id', $id)->first()?->markAsRead();

        return response()->noContent();
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->noContent();
    }
}