<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Facades\CauserResolver;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsAdminActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admin')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->logExcept($this->getActivitylogExceptAttributes())
            ->setDescriptionForEvent(function (string $eventName): string {
                return sprintf('%s %s', $eventName, Str::lower($this->getActivitylogSubjectLabel()));
            });
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $properties = $activity->properties?->toArray() ?? [];
        $properties['subject_label'] = $this->getActivitylogSubjectLabel();
        $properties['subject_name'] = $this->getActivitylogSubjectName();
        $properties['causer_name'] = $this->getActivitylogCauserName();
        $properties['event_name'] = $eventName;

        $activity->properties = collect($properties);
    }

    protected function getActivitylogExceptAttributes(): array
    {
        return ['password', 'remember_token'];
    }

    protected function getActivitylogSubjectLabel(): string
    {
        return Str::headline(class_basename(static::class));
    }

    protected function getActivitylogSubjectName(): string
    {
        $candidates = [
            'fullname',
            'title',
            'name',
            'label',
            'question',
            'question_text',
            'order_code',
            'invoice_code',
            'code',
            'platform',
            'slug',
            'organization_name',
            'site_name',
        ];

        foreach ($candidates as $attribute) {
            $formatted = $this->formatActivitylogAttribute($this->getAttribute($attribute));

            if ($formatted !== null) {
                return $formatted;
            }
        }

        $key = $this->getKey();

        return $key === null ? 'tanpa identitas' : "#{$key}";
    }

    protected function getActivitylogCauserName(): ?string
    {
        if (app()->bound('activity-log-causer-name')) {
            return $this->formatActivitylogAttribute(app('activity-log-causer-name'));
        }

        $resolvedCauser = CauserResolver::resolve();

        if ($resolvedCauser) {
            return $this->formatActivitylogAttribute($resolvedCauser->fullname ?? $resolvedCauser->name ?? null);
        }

        $requestUser = request()?->user();

        if ($requestUser) {
            return $this->formatActivitylogAttribute($requestUser->fullname ?? $requestUser->name ?? null);
        }

        $guards = config('sanctum.guard', []);

        if (is_string($guards)) {
            $guards = [$guards];
        }

        $guards = array_values(array_unique(array_filter([
            ...$guards,
            config('activitylog.default_auth_driver'),
            'sanctum',
            config('auth.defaults.guard'),
            'web',
        ])));

        foreach ($guards as $guard) {
            $user = auth($guard)->user();

            if ($user) {
                return $this->formatActivitylogAttribute($user->fullname ?? $user->name ?? null);
            }
        }

        return $this->formatActivitylogAttribute(auth()->user()?->fullname ?? auth()->user()?->name ?? null);
    }

    protected function formatActivitylogAttribute(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));

        if ($normalized === '') {
            return null;
        }

        return Str::limit($normalized, 80);
    }
}
