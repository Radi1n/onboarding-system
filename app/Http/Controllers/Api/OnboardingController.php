<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Onboarding;
use App\Models\TaskTemplate;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    // HR يبدأ Onboarding لموظف، والمهام تتولّد تلقائياً من القوالب
    public function start(Request $request, Employee $employee)
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

        $employee->loadMissing('user');

        Activity::log(
            $request->user(),
            'onboarding.started',
            $onboarding,
            "Started onboarding for {$employee->user->name}",
            null,
            ['status' => 'employee_pending']
        );

        Activity::notify(
            [$employee->user],
            $onboarding,
            'Welcome aboard',
            'HR started your onboarding. Please upload your documents.'
        );

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

        return $onboarding->load(
            'employee.user:id,name,email',
            'employee.department:id,name',
            'employee.manager:id,name',
            'tasks',
            'documents'
        );
    }

    // الموظف يسلّم بياناته، فينتقل الـOnboarding لمراجعة HR
    public function submit(Request $request, Onboarding $onboarding)
    {
        $onboarding->loadMissing('employee.user');

        if ($onboarding->employee->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($onboarding->status !== 'employee_pending') {
            return response()->json(['message' => 'Onboarding is not waiting for employee.'], 422);
        }

        // لازم الملفين يكونون مرفوعين وما فيه ملف مرفوض
        $docs = $onboarding->documents()->pluck('status', 'type');

        if (! $docs->has('national_id') || ! $docs->has('contract')) {
            return response()->json([
                'message' => 'Please upload both your national ID and signed contract before submitting.',
            ], 422);
        }

        if ($docs->contains('rejected')) {
            return response()->json([
                'message' => 'A document was rejected. Please upload it again before submitting.',
            ], 422);
        }

        DB::transaction(function () use ($onboarding) {
            $onboarding->tasks()
                ->where('assigned_role', 'employee')
                ->update(['status' => 'done', 'completed_at' => now()]);

            $onboarding->update(['status' => 'hr_review']);
        });

        $name = $onboarding->employee->user->name;

        Activity::log(
            $request->user(),
            'onboarding.submitted',
            $onboarding,
            "{$name} submitted their documents",
            ['status' => 'employee_pending'],
            ['status' => 'hr_review']
        );

        Activity::notify(
            Activity::byRole('hr'),
            $onboarding,
            'Ready for HR review',
            "{$name} submitted their documents."
        );

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

        // ما نمرّر لـIT إلا لما الملفين معتمدين
        if ($data['decision'] === 'approve') {
            $statuses = $onboarding->documents()->pluck('status');

            if ($statuses->count() < 2 || $statuses->contains(fn ($s) => $s !== 'approved')) {
                return response()->json([
                    'message' => 'All documents must be approved first.',
                ], 422);
            }
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

        $onboarding->loadMissing('employee.user');
        $name = $onboarding->employee->user->name;

        if ($data['decision'] === 'approve') {
            Activity::log(
                $request->user(),
                'onboarding.hr_approved',
                $onboarding,
                "HR approved {$name}'s documents",
                ['status' => 'hr_review'],
                ['status' => 'it_setup']
            );

            Activity::notify(
                Activity::byRole('it'),
                $onboarding,
                'IT setup needed',
                "{$name} needs accounts and equipment."
            );
        } else {
            Activity::log(
                $request->user(),
                'onboarding.hr_returned',
                $onboarding,
                "HR returned {$name}'s onboarding",
                ['status' => 'hr_review'],
                ['status' => 'employee_pending', 'comment' => $data['comment'] ?? null]
            );

            Activity::notify(
                [$onboarding->employee->user],
                $onboarding,
                'Action needed',
                'HR returned your onboarding. Please check the rejected documents and submit again.'
            );
        }

        return $onboarding->load('tasks');
    }
}