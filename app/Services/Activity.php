<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Onboarding;
use App\Models\User;
use App\Notifications\OnboardingUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Activity
{
    // يسجّل حدث في سجل التدقيق
    public static function log(
        ?User $actor,
        string $action,
        Model $entity,
        string $description,
        ?array $old = null,
        ?array $new = null
    ): void {
        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->getKey(),
            'description' => $description,
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    // كل المستخدمين الفعّالين بدور معيّن
    public static function byRole(string ...$roles): Collection
    {
        return User::whereHas('role', fn ($q) => $q->whereIn('name', $roles))
            ->where('is_active', true)
            ->get();
    }

    // مدير الموظف، وإذا ما له مدير نرسل للأدمن
    public static function managersOf(Onboarding $onboarding): Collection
    {
        $managerId = $onboarding->employee->manager_id;
        $manager = $managerId ? User::find($managerId) : null;

        return $manager ? collect([$manager]) : self::byRole('admin');
    }

    // يرسل إشعار لمجموعة مستخدمين
    public static function notify(iterable $users, Onboarding $onboarding, string $title, string $body): void
    {
        foreach ($users as $user) {
            if ($user) {
                $user->notify(new OnboardingUpdated($onboarding->id, $title, $body));
            }
        }
    }
}