<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingStepController extends Controller
{
    // IT يكمل مهمة، وإذا خلصت كل مهام IT ينتقل الـOnboarding لاعتماد المدير
    public function completeTask(Request $request, OnboardingTask $task)
    {
        $onboarding = $task->onboarding;

        if ($task->assigned_role !== 'it') {
            return response()->json(['message' => 'Only IT tasks can be completed here.'], 422);
        }

        if ($onboarding->status !== 'it_setup') {
            return response()->json(['message' => 'Onboarding is not in IT setup.'], 422);
        }

        if ($task->status === 'done') {
            return response()->json(['message' => 'Task already completed.'], 422);
        }

        DB::transaction(function () use ($task, $onboarding, $request) {
            $task->update([
                'status' => 'done',
                'completed_at' => now(),
                'assigned_to' => $request->user()->id,
            ]);

            $itRemaining = $onboarding->tasks()
                ->where('assigned_role', 'it')
                ->where('status', '!=', 'done')
                ->exists();

            if (! $itRemaining) {
                $onboarding->update(['status' => 'manager_approval']);
            }
        });

        return $onboarding->fresh()->load('tasks');
    }

    // المدير يعتمد أو يرجّع الـOnboarding لـIT
    public function managerDecision(Request $request, Onboarding $onboarding)
    {
        $user = $request->user();

        if ($user->hasRole('manager') && $onboarding->employee->manager_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'comment' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
        ]);

        if ($onboarding->status !== 'manager_approval') {
            return response()->json(['message' => 'Onboarding is not waiting for manager.'], 422);
        }

        DB::transaction(function () use ($onboarding, $data) {
            if ($data['decision'] === 'approve') {
                $onboarding->tasks()
                    ->where('assigned_role', 'manager')
                    ->update(['status' => 'done', 'completed_at' => now()]);

                $onboarding->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            } else {
                // نرجّع مهام IT pending عشان يعيدونها
                $onboarding->tasks()
                    ->where('assigned_role', 'it')
                    ->update(['status' => 'pending', 'completed_at' => null]);

                $onboarding->update(['status' => 'it_setup']);
            }
        });

        return $onboarding->fresh()->load('tasks');
    }
}