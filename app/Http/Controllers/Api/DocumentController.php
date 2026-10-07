<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Onboarding;
use App\Models\User;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    private const LABELS = [
        'national_id' => 'National ID',
        'contract' => 'Signed contract',
    ];

    private function canAccess(User $user, Onboarding $onboarding): bool
    {
        $employee = $onboarding->employee;

        return match (true) {
            $user->hasRole('admin', 'hr') => true,
            $user->hasRole('employee') => $employee->user_id === $user->id,
            $user->hasRole('manager') => $employee->manager_id === $user->id,
            default => false,
        };
    }

    public function index(Request $request, Onboarding $onboarding)
    {
        if (! $this->canAccess($request->user(), $onboarding)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $onboarding->documents()->get();
    }

    public function store(Request $request, Onboarding $onboarding)
    {
        $user = $request->user();

        if (! $user->hasRole('admin', 'hr', 'employee') || ! $this->canAccess($user, $onboarding)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->hasRole('employee') && $onboarding->status !== 'employee_pending') {
            return response()->json(['message' => 'Documents can only be uploaded while onboarding is waiting for you.'], 422);
        }

        $data = $request->validate([
            'type' => ['required', 'in:national_id,contract'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        // لو فيه ملف قديم من نفس النوع نحذفه ونستبدله
        $existing = $onboarding->documents()->where('type', $data['type'])->first();
        if ($existing) {
            Storage::disk('local')->delete($existing->file_path);
        }

        $file = $request->file('file');
        $path = $file->store("documents/{$onboarding->id}", 'local');

        $document = Document::updateOrCreate(
            ['onboarding_id' => $onboarding->id, 'type' => $data['type']],
            [
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'status' => 'pending',
                'reject_reason' => null,
                'reviewed_by' => null,
            ]
        );

        Activity::log(
            $user,
            'document.uploaded',
            $document,
            "{$user->name} uploaded the ".self::LABELS[$data['type']]
        );

        return response()->json($document, 201);
    }

    // HR يقبل أو يرفض ملف
    public function review(Request $request, Document $document)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
        ]);

        if ($document->onboarding->status !== 'hr_review') {
            return response()->json(['message' => 'Onboarding is not in HR review.'], 422);
        }

        $old = $document->status;
        $new = $data['decision'] === 'approve' ? 'approved' : 'rejected';

        $document->update([
            'status' => $new,
            'reject_reason' => $new === 'rejected' ? $data['reason'] : null,
            'reviewed_by' => $request->user()->id,
        ]);

        Activity::log(
            $request->user(),
            "document.{$new}",
            $document,
            "{$request->user()->name} {$new} the ".self::LABELS[$document->type],
            ['status' => $old],
            ['status' => $new, 'reason' => $document->reject_reason]
        );

        return $document;
    }

    public function download(Request $request, Document $document)
    {
        if (! $this->canAccess($request->user(), $document->onboarding)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! Storage::disk('local')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }
}