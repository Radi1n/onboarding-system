<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use App\Services\Activity;
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

        $advanced = DB::transaction(function () use ($task, $onboarding, $request) {
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

                return true;
            }

            return false;
        });

        $onboarding->loadMissing('employee.user');
        $name = $onboarding->employee->user->name;

        Activity::log(
            $request->user(),
            'task.completed',
            $task,
            "{$request->user()->name} completed \"{$task->title}\" for {$name}"
        );

        if ($advanced) {
            Activity::log(
                $request->user(),
                'onboarding.it_completed',
                $onboarding,
                "IT finished the setup for {$name}",
                ['status' => 'it_setup'],
                ['status' => 'manager_approval']
            );

            Activity::notify(
                Activity::managersOf($onboarding),
                $onboarding,
                'Waiting for your approval',
                "{$name}'s setup is ready for final approval."
            );
        }

        return $onboarding->fresh()->load('tasks');
    }

    // المدير يعتمد أو يرجّع الـOnboarding لـIT
    public function managerDecision(Request $request, Onboarding $onboarding)
    {
        $user = $request->user();
        $onboarding->loadMissing('employee.user');

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

        $name = $onboarding->employee->user->name;

        if ($data['decision'] === 'approve') {
            Activity::log(
                $user,
                'onboarding.completed',
                $onboarding,
                "{$user->name} approved {$name}'s onboarding",
                ['status' => 'manager_approval'],
                ['status' => 'completed']
            );

            Activity::notify(
                [$onboarding->employee->user],
                $onboarding,
                'Onboarding completed',
                'Everything is done. Welcome aboard!'
            );

            Activity::notify(
                Activity::byRole('hr'),
                $onboarding,
                'Onboarding completed',
                "{$name}'s onboarding is complete."
            );
        } else {
            Activity::log(
                $user,
                'onboarding.manager_returned',
                $onboarding,
                "{$user->name} sent {$name}'s onboarding back to IT",
                ['status' => 'manager_approval'],
                ['status' => 'it_setup', 'comment' => $data['comment'] ?? null]
            );

            Activity::notify(
                Activity::byRole('it'),
                $onboarding,
                'Sent back by manager',
                "{$name}: {$data['comment']}"
            );
        }

        return $onboarding->fresh()->load('tasks');
    }
}