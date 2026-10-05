<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Onboarding;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();

        $onboardings = Onboarding::query();

        // كل دور يشوف إحصائيات تخصه
        if ($user->hasRole('employee')) {
            $onboardings->whereHas('employee', fn ($q) => $q->where('user_id', $user->id));
        } elseif ($user->hasRole('manager')) {
            $onboardings->whereHas('employee', fn ($q) => $q->where('manager_id', $user->id));
        }

        $statusFilter = match (true) {
            $user->hasRole('hr') => 'hr_review',
            $user->hasRole('it') => 'it_setup',
            $user->hasRole('manager') => 'manager_approval',
            default => null,
        };

        return [
            'active' => (clone $onboardings)->where('status', '!=', 'completed')->count(),
            'pending_review' => $statusFilter
                ? (clone $onboardings)->where('status', $statusFilter)->count()
                : (clone $onboardings)->whereIn('status', ['hr_review', 'it_setup', 'manager_approval'])->count(),
            'completed' => (clone $onboardings)->where('status', 'completed')->count(),
            'documents_to_review' => $user->hasRole('admin', 'hr')
                ? Document::where('status', 'pending')->count()
                : 0,
        ];
    }
}