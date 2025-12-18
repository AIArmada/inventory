<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Jobs\WriteAuditLogJob;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Log an audit event.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        AuditEventType $eventType,
        ?Model $subject = null,
        ?Model $target = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?AuditSeverity $severity = null
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $data = [
            'event_type' => $eventType,
            'severity' => $severity ?? $eventType->defaultSeverity(),
            'actor_type' => $this->getActorType(),
            'actor_id' => $this->getActorId(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'old_value' => $oldValues,
            'new_value' => $newValues,
            'context' => $this->enrichMetadata($metadata),
            'occurred_at' => now(),
        ];

        if ($this->shouldWriteAsync()) {
            WriteAuditLogJob::dispatch($data);
        } else {
            PermissionAuditLog::create($data);
        }
    }

    /**
     * Log a permission granted event.
     */
    public function logPermissionGranted(Model $subject, string $permission, ?Model $target = null): void
    {
        $this->log(
            eventType: AuditEventType::PermissionGranted,
            subject: $subject,
            target: $target,
            newValues: ['permission' => $permission]
        );
    }

    /**
     * Log a permission revoked event.
     */
    public function logPermissionRevoked(Model $subject, string $permission, ?Model $target = null): void
    {
        $this->log(
            eventType: AuditEventType::PermissionRevoked,
            subject: $subject,
            target: $target,
            oldValues: ['permission' => $permission]
        );
    }

    /**
     * Log a role assigned event.
     */
    public function logRoleAssigned(Model $user, string $role): void
    {
        $this->log(
            eventType: AuditEventType::RoleAssigned,
            subject: $user,
            newValues: ['role' => $role]
        );
    }

    /**
     * Log a role removed event.
     */
    public function logRoleRemoved(Model $user, string $role): void
    {
        $this->log(
            eventType: AuditEventType::RoleRemoved,
            subject: $user,
            oldValues: ['role' => $role]
        );
    }

    /**
     * Log a role created event.
     */
    public function logRoleCreated(Model $role): void
    {
        $this->log(
            eventType: AuditEventType::RoleCreated,
            subject: $role,
            newValues: ['name' => $role->name ?? null]
        );
    }

    /**
     * Log a role deleted event.
     */
    public function logRoleDeleted(Model $role): void
    {
        $this->log(
            eventType: AuditEventType::RoleDeleted,
            subject: $role,
            oldValues: ['name' => $role->name ?? null],
            severity: AuditSeverity::High
        );
    }

    /**
     * Log an access denied event.
     *
     * @param  array<string, mixed>|null  $context
     */
    public function logAccessDenied(Model $user, string $permission, ?Model $resource = null, ?array $context = null): void
    {
        $this->log(
            eventType: AuditEventType::AccessDenied,
            subject: $user,
            target: $resource,
            metadata: array_merge(
                ['permission' => $permission],
                $context ?? []
            )
        );
    }

    /**
     * Log a policy evaluated event.
     *
     * @param  array<string, mixed>  $evaluation
     */
    public function logPolicyEvaluated(string $action, string $resource, array $evaluation): void
    {
        $this->log(
            eventType: AuditEventType::PolicyEvaluated,
            metadata: [
                'action' => $action,
                'resource' => $resource,
                'evaluation' => $evaluation,
            ]
        );
    }

    /**
     * Log a suspicious activity event.
     *
     * @param  array<string, mixed>  $details
     */
    public function logSuspiciousActivity(Model $user, string $activity, array $details = []): void
    {
        $this->log(
            eventType: AuditEventType::SuspiciousActivity,
            subject: $user,
            metadata: array_merge(['activity' => $activity], $details),
            severity: AuditSeverity::Critical
        );
    }

    /**
     * Log a privilege escalation event.
     *
     * @param  array<string>  $newPrivileges
     */
    public function logPrivilegeEscalation(Model $user, array $newPrivileges): void
    {
        $this->log(
            eventType: AuditEventType::PrivilegeEscalation,
            subject: $user,
            newValues: ['privileges' => $newPrivileges],
            severity: AuditSeverity::Critical
        );
    }

    /**
     * Log a bulk operation event.
     *
     * @param  array<string, mixed>  $details
     */
    public function logBulkOperation(string $operation, int $affectedCount, array $details = []): void
    {
        $this->log(
            eventType: AuditEventType::BulkOperation,
            metadata: [
                'operation' => $operation,
                'affected_count' => $affectedCount,
                ...$details,
            ],
            severity: $affectedCount > 100 ? AuditSeverity::High : AuditSeverity::Medium
        );
    }

    /**
     * Check if audit logging is enabled.
     */
    protected function isEnabled(): bool
    {
        return (bool) config('filament-authz.audit.enabled', true);
    }

    /**
     * Check if audit should be written asynchronously.
     */
    protected function shouldWriteAsync(): bool
    {
        return (bool) config('filament-authz.audit.async', false);
    }

    /**
     * Get the current actor type.
     */
    protected function getActorType(): ?string
    {
        $user = Auth::user();

        /** @phpstan-ignore method.notFound */
        return $user?->getMorphClass();
    }

    /**
     * Get the current actor ID.
     */
    protected function getActorId(): ?string
    {
        $user = Auth::user();

        return $user !== null ? (string) $user->getAuthIdentifier() : null;
    }

    /**
     * Enrich metadata with request information.
     *
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    protected function enrichMetadata(?array $metadata): array
    {
        $enriched = $metadata ?? [];

        if (request()->hasSession()) {
            $enriched['session_id'] = session()->getId();
        }

        $enriched['ip_address'] = request()->ip();
        $enriched['user_agent'] = request()->userAgent();
        $enriched['url'] = request()->fullUrl();
        $enriched['method'] = request()->method();

        return $enriched;
    }
}
