<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityLogService
{
    public function getAllForAdmin(string $search = '', int $perPage = 10, ?string $event = null): LengthAwarePaginator
    {
        $perPage = max($perPage, 1);

        return $this->buildAdminActivityQuery($search, $event)->paginate($perPage);
    }

    public function getRecentAdminActivities(int $limit = 8): array
    {
        return $this->buildAdminActivityQuery()
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity): array => $this->mapActivity($activity))
            ->values()
            ->all();
    }

    public function mapActivity(Activity $activity): array
    {
        $subjectLabel = $this->resolveSubjectLabel($activity);
        $subjectName = $this->resolveSubjectName($activity);

        return [
            'id' => $activity->id,
            'activity' => $this->formatActivityText(
                $subjectLabel,
                $subjectName,
                $activity->event,
                $activity->description ?? ''
            ),
            'actor' => $this->resolveActorName($activity),
            'event' => $activity->event,
            'subject_label' => $subjectLabel,
            'subject_name' => $subjectName,
            'description' => $activity->description,
            'changed_fields' => $this->resolveChangedFields($activity),
            'occurred_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    private function buildAdminActivityQuery(string $search = '', ?string $event = null): Builder
    {
        return Activity::query()
            ->with(['causer' => function ($query) {
                $query->withTrashed();
            }])
            ->where('log_name', 'admin')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder->where('description', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhere('properties', 'like', "%{$search}%")
                        ->orWhereHasMorph('causer', [User::class], function (Builder $causerQuery) use ($search) {
                            $causerQuery->where('fullname', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($event !== null && $event !== '', function (Builder $query) use ($event) {
                $query->where('event', $event);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function resolveActorName(Activity $activity): string
    {
        $properties = $activity->properties?->toArray() ?? [];

        return $this->sanitizeText(
            $activity->causer?->fullname
            ?? $activity->causer?->name
            ?? Arr::get($properties, 'causer_name')
        ) ?? 'Sistem';
    }

    private function resolveSubjectLabel(Activity $activity): string
    {
        $properties = $activity->properties?->toArray() ?? [];

        return $this->sanitizeText(
            Arr::get($properties, 'subject_label')
        ) ?? Str::headline(class_basename((string) $activity->subject_type));
    }

    private function resolveSubjectName(Activity $activity): ?string
    {
        $properties = $activity->properties?->toArray() ?? [];

        return $this->sanitizeText(
            Arr::get($properties, 'subject_name')
        );
    }

    private function resolveChangedFields(Activity $activity): array
    {
        $properties = $activity->properties?->toArray() ?? [];
        $attributes = Arr::get($properties, 'attributes', []);
        $old = Arr::get($properties, 'old', []);

        return collect(array_merge(array_keys($attributes), array_keys($old)))
            ->filter(fn (mixed $field): bool => is_string($field) && $field !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function formatActivityText(
        string $subjectLabel,
        ?string $subjectName,
        ?string $event,
        string $description,
    ): string {
        $action = match ($event) {
            'created' => 'ditambahkan',
            'updated' => 'diperbarui',
            'deleted' => 'dihapus',
            default => $description !== '' ? $description : 'diproses',
        };

        $subject = $subjectLabel;

        if ($subjectName !== null && $subjectName !== 'tanpa identitas') {
            $subject .= " {$subjectName}";
        }

        return trim("{$subject} {$action}");
    }

    private function sanitizeText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));

        if ($normalized === '') {
            return null;
        }

        return Str::limit($normalized, 120);
    }
}
