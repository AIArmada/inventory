<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ComplianceReportGenerator
{
    /**
     * Generate a SOC2 access review report.
     *
     * @return array<string, mixed>
     */
    public function generateSoc2Report(): array
    {
        $report = [
            'title' => 'SOC2 Access Review Report',
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'start' => now()->subDays(90)->toIso8601String(),
                'end' => now()->toIso8601String(),
            ],
            'sections' => [],
        ];

        // User Access Summary
        $userModel = config('filament-authz.user_model', 'App\\Models\\User');
        $userCount = 0;
        $usersWithRoles = 0;

        if (class_exists($userModel)) {
            $userCount = $userModel::count();
            $usersWithRoles = DB::table('model_has_roles')
                ->where('model_type', $userModel)
                ->distinct('model_id')
                ->count('model_id');
        }

        $report['sections']['user_access'] = [
            'title' => 'User Access Summary',
            'data' => [
                'total_users' => $userCount,
                'users_with_roles' => $usersWithRoles,
                'users_without_roles' => $userCount - $usersWithRoles,
            ],
        ];

        // Role Distribution
        $roleDistribution = Role::withCount(['users', 'permissions'])
            ->get()
            ->map(fn ($role) => [
                'name' => $role->name,
                'user_count' => $role->users_count ?? 0,
                'permission_count' => $role->permissions_count ?? 0,
            ])
            ->toArray();

        $report['sections']['role_distribution'] = [
            'title' => 'Role Distribution',
            'data' => $roleDistribution,
        ];

        // Privileged Access
        $superAdminRole = config('filament-authz.super_admin_role', 'super_admin');
        $superAdminCount = 0;

        if (class_exists($userModel) && method_exists($userModel, 'role')) {
            $superAdminCount = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', $superAdminRole)
                ->count();
        }

        $report['sections']['privileged_access'] = [
            'title' => 'Privileged Access',
            'data' => [
                'super_admin_role' => $superAdminRole,
                'super_admin_count' => $superAdminCount,
                'recommendation' => $superAdminCount > 3 ? 'Review super admin count' : 'Within acceptable range',
            ],
        ];

        // Permission Count
        $report['sections']['permission_summary'] = [
            'title' => 'Permission Summary',
            'data' => [
                'total_permissions' => Permission::count(),
                'total_roles' => Role::count(),
            ],
        ];

        return $report;
    }

    /**
     * Generate segregation of duties analysis.
     *
     * @return array<string, mixed>
     */
    public function generateSegregationOfDutiesReport(): array
    {
        $conflictingPermissions = [
            ['create_payment', 'approve_payment'],
            ['create_order', 'approve_order'],
            ['edit_user', 'delete_user'],
            ['create.invoice', 'approve.invoice'],
            ['write_check', 'sign_check'],
        ];

        $violations = [];

        foreach ($conflictingPermissions as $conflict) {
            // Find roles that have both conflicting permissions
            $rolesWithConflict = Role::whereHas('permissions', function ($q) use ($conflict) {
                $q->whereIn('name', $conflict);
            }, '=', count($conflict))->get();

            if ($rolesWithConflict->isNotEmpty()) {
                $violations[] = [
                    'permissions' => $conflict,
                    'roles' => $rolesWithConflict->pluck('name')->toArray(),
                    'severity' => 'high',
                    'recommendation' => 'Split these permissions across different roles',
                ];
            }
        }

        return [
            'title' => 'Segregation of Duties Analysis',
            'generated_at' => now()->toIso8601String(),
            'violations' => $violations,
            'status' => count($violations) === 0 ? 'pass' : 'fail',
            'score' => count($violations) === 0 ? 100 : max(0, 100 - (count($violations) * 20)),
        ];
    }

    /**
     * Generate GDPR data access report.
     *
     * @return array<string, mixed>
     */
    public function generateGdprReport(): array
    {
        // Find permissions related to personal data
        $piiPermissions = Permission::where('name', 'like', '%user%')
            ->orWhere('name', 'like', '%customer%')
            ->orWhere('name', 'like', '%personal%')
            ->orWhere('name', 'like', '%profile%')
            ->orWhere('name', 'like', '%email%')
            ->orWhere('name', 'like', '%phone%')
            ->orWhere('name', 'like', '%address%')
            ->get();

        $rolesWithPiiAccess = Role::whereHas('permissions', function ($q) use ($piiPermissions) {
            $q->whereIn('id', $piiPermissions->pluck('id'));
        })->get();

        $userModel = config('filament-authz.user_model', 'App\\Models\\User');
        $usersWithPiiAccess = 0;

        if (class_exists($userModel)) {
            $usersWithPiiAccess = DB::table('model_has_roles')
                ->whereIn('role_id', $rolesWithPiiAccess->pluck('id'))
                ->where('model_type', $userModel)
                ->distinct('model_id')
                ->count('model_id');
        }

        return [
            'title' => 'GDPR Data Access Report',
            'generated_at' => now()->toIso8601String(),
            'pii_permissions' => $piiPermissions->pluck('name')->toArray(),
            'roles_with_pii_access' => $rolesWithPiiAccess->map(fn ($r) => [
                'name' => $r->name,
                'permissions' => $r->permissions->pluck('name')->toArray(),
            ])->toArray(),
            'users_with_pii_access' => $usersWithPiiAccess,
            'recommendations' => [
                'Regularly review users with access to personal data',
                'Implement data access logging for PII fields',
                'Consider implementing data anonymization for non-production environments',
            ],
        ];
    }

    /**
     * Calculate overall compliance score.
     *
     * @return array<string, mixed>
     */
    public function calculateComplianceScore(): array
    {
        $scores = [];

        // SOC2 criteria
        $soc2 = $this->generateSoc2Report();
        $userAccessScore = ($soc2['sections']['user_access']['data']['users_with_roles'] / max(1, $soc2['sections']['user_access']['data']['total_users'])) * 100;
        $scores['user_access'] = min(100, $userAccessScore);

        // SoD criteria
        $sod = $this->generateSegregationOfDutiesReport();
        $scores['segregation_of_duties'] = $sod['score'];

        // Super admin ratio
        $superAdminCount = $soc2['sections']['privileged_access']['data']['super_admin_count'];
        $totalUsers = $soc2['sections']['user_access']['data']['total_users'];
        $superAdminRatio = $totalUsers > 0 ? ($superAdminCount / $totalUsers) * 100 : 0;
        $scores['privileged_access'] = $superAdminRatio < 5 ? 100 : max(0, 100 - ($superAdminRatio * 5));

        $overallScore = array_sum($scores) / count($scores);

        return [
            'overall_score' => round($overallScore, 1),
            'grade' => $this->getGrade($overallScore),
            'breakdown' => $scores,
            'generated_at' => now()->toIso8601String(),
            'recommendations' => $this->getRecommendations($scores),
        ];
    }

    /**
     * Export report to JSON.
     */
    public function exportToJson(string $reportType): string
    {
        $report = match ($reportType) {
            'soc2' => $this->generateSoc2Report(),
            'sod' => $this->generateSegregationOfDutiesReport(),
            'gdpr' => $this->generateGdprReport(),
            'score' => $this->calculateComplianceScore(),
            default => throw new InvalidArgumentException("Unknown report type: {$reportType}"),
        };

        return json_encode($report, JSON_PRETTY_PRINT);
    }

    protected function getGrade(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };
    }

    /**
     * @param  array<string, float>  $scores
     * @return array<string>
     */
    protected function getRecommendations(array $scores): array
    {
        $recommendations = [];

        if ($scores['user_access'] < 80) {
            $recommendations[] = 'Review and assign roles to users without roles';
        }

        if ($scores['segregation_of_duties'] < 80) {
            $recommendations[] = 'Address segregation of duties violations';
        }

        if ($scores['privileged_access'] < 80) {
            $recommendations[] = 'Reduce the number of super admin users';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Compliance posture is good. Continue regular reviews.';
        }

        return $recommendations;
    }
}
