<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Onboarding;
use App\Models\TaskTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    // HR يبدأ Onboarding لموظف، والمهام تتولّد تلقائياً من القوالب
    public function start(Employee $employee)
    {
        if ($employee->onboarding()->exists()) {
            return response()->json(['message' => 'Onboarding already started.'], 409);
        }

        $onboarding = DB::transaction(function () use ($employee) {
            $onboarding = Onboarding::create([
                'employee_id' => $employee->id,
                'status' => 'employee_pending',
                'started_at' => now(),
            ]);

            foreach (TaskTemplate::orderBy('order')->get() as $template) {
                $onboarding->tasks()->create([
                    'title' => $template->title,
                    'assigned_role' => $template->assigned_role,
                    'assigned_to' => match ($template->assigned_role) {
                        'employee' => $employee->user_id,
                        'manager' => $employee->manager_id,
                        default => null,
                    },
                    'due_date' => now()->addDays(7),
                ]);
            }

            return $onboarding;
        });

        return response()->json($onboarding->load('employee.user:id,name', 'tasks'), 201);
    }

    public function show(Request $request, Onboarding $onboarding)
    {
        $user = $request->user();
        $employee = $onboarding->employee;

        if ($user->hasRole('employee') && $employee->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->hasRole('manager') && $employee->manager_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $onboarding->load('employee.user:id,name', 'tasks');
    }

    // الموظف يسلّم بياناته، فينتقل الـOnboarding لمراجعة HR
    public function submit(Request $request, Onboarding $onboarding)
    {
        if ($onboarding->employee->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($onboarding->status !== 'employee_pending') {
            return response()->json(['message' => 'Onboarding is not waiting for employee.'], 422);
        }

        DB::transaction(function () use ($onboarding) {
            $onboarding->tasks()
                ->where('assigned_role', 'employee')
                ->update(['status' => 'done', 'completed_at' => now()]);

            $onboarding->update(['status' => 'hr_review']);
        });

        return $onboarding->load('tasks');
    }

    // HR يقبل أو يرجّع الـOnboarding للموظف
    public function hrDecision(Request $request, Onboarding $onboarding)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'comment' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
        ]);

        if ($onboarding->status !== 'hr_review') {
            return response()->json(['message' => 'Onboarding is not in HR review.'], 422);
        }

        DB::transaction(function () use ($onboarding, $data) {
            if ($data['decision'] === 'approve') {
                $onboarding->tasks()
                    ->where('assigned_role', 'hr')
                    ->update(['status' => 'done', 'completed_at' => now()]);

                $onboarding->update(['status' => 'it_setup']);
            } else {
                // نرجّع مهام الموظفة pending عشان تعيد التسليم
                $onboarding->tasks()
                    ->where('assigned_role', 'employee')
                    ->update(['status' => 'pending', 'completed_at' => null]);

                $onboarding->update(['status' => 'employee_pending']);
            }
        });

        return $onboarding->load('tasks');
    }
}