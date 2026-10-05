<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    private const RELATIONS = [
        'user:id,name,email',
        'department:id,name',
        'manager:id,name',
        'onboarding:id,employee_id,status',
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Employee::with(self::RELATIONS);

        if ($user->hasRole('employee')) {
            $query->where('user_id', $user->id);
        } elseif ($user->hasRole('manager')) {
            $query->where('manager_id', $user->id);
        }

        return $query->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'start_date' => ['nullable', 'date'],
        ]);

        $employee = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role_id' => Role::where('name', 'employee')->value('id'),
            ]);

            return Employee::create([
                'user_id' => $user->id,
                'department_id' => $data['department_id'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'phone' => $data['phone'] ?? null,
                'start_date' => $data['start_date'] ?? null,
            ]);
        });

        return response()->json($employee->load(self::RELATIONS), 201);
    }

    public function show(Request $request, Employee $employee)
    {
        $user = $request->user();

        if ($user->hasRole('employee') && $employee->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->hasRole('manager') && $employee->manager_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $employee->load(self::RELATIONS);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'start_date' => ['nullable', 'date'],
        ]);

        $employee->update($data);

        return $employee->load(self::RELATIONS);
    }

    public function destroy(Employee $employee)
    {
        $employee->user()->delete(); // يحذف المستخدم والموظف (cascade)

        return response()->noContent();
    }
}