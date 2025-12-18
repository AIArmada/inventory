<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Enums\ConditionOperator;
use AIArmada\FilamentAuthz\Enums\ImpactLevel;
use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Enums\PolicyCombiningAlgorithm;
use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;

describe('AuditSeverity enum', function (): void {
    it('has all expected cases', function (): void {
        expect(AuditSeverity::cases())->toHaveCount(4);
        expect(AuditSeverity::Low->value)->toBe('low');
        expect(AuditSeverity::Medium->value)->toBe('medium');
        expect(AuditSeverity::High->value)->toBe('high');
        expect(AuditSeverity::Critical->value)->toBe('critical');
    });

    it('returns correct labels', function (): void {
        expect(AuditSeverity::Low->label())->toBe('Low');
        expect(AuditSeverity::Medium->label())->toBe('Medium');
        expect(AuditSeverity::High->label())->toBe('High');
        expect(AuditSeverity::Critical->label())->toBe('Critical');
    });

    it('returns descriptions', function (): void {
        foreach (AuditSeverity::cases() as $severity) {
            expect($severity->description())->toBeString()->not->toBeEmpty();
        }
    });

    it('returns colors', function (): void {
        expect(AuditSeverity::Low->color())->toBe('gray');
        expect(AuditSeverity::Medium->color())->toBe('info');
        expect(AuditSeverity::High->color())->toBe('warning');
        expect(AuditSeverity::Critical->color())->toBe('danger');
    });

    it('returns icons', function (): void {
        foreach (AuditSeverity::cases() as $severity) {
            expect($severity->icon())->toBeString()->toStartWith('heroicon-o-');
        }
    });

    it('returns numeric levels', function (): void {
        expect(AuditSeverity::Low->numericLevel())->toBe(1);
        expect(AuditSeverity::Medium->numericLevel())->toBe(2);
        expect(AuditSeverity::High->numericLevel())->toBe(3);
        expect(AuditSeverity::Critical->numericLevel())->toBe(4);
    });

    it('determines if should notify', function (): void {
        expect(AuditSeverity::Low->shouldNotify())->toBeFalse();
        expect(AuditSeverity::Medium->shouldNotify())->toBeFalse();
        expect(AuditSeverity::High->shouldNotify())->toBeTrue();
        expect(AuditSeverity::Critical->shouldNotify())->toBeTrue();
    });

    it('returns retention days', function (): void {
        expect(AuditSeverity::Low->retentionDays())->toBe(30);
        expect(AuditSeverity::Medium->retentionDays())->toBe(90);
        expect(AuditSeverity::High->retentionDays())->toBe(365);
        expect(AuditSeverity::Critical->retentionDays())->toBe(730);
    });
});

describe('ConditionOperator enum', function (): void {
    it('has all expected cases', function (): void {
        expect(ConditionOperator::cases())->not->toBeEmpty();
    });

    it('returns correct labels', function (): void {
        expect(ConditionOperator::Equals->label())->toBe('Equals');
        expect(ConditionOperator::NotEquals->label())->toBe('Not Equals');
        expect(ConditionOperator::GreaterThan->label())->toBe('Greater Than');
        expect(ConditionOperator::Contains->label())->toBe('Contains');
        expect(ConditionOperator::In->label())->toBe('In List');
        expect(ConditionOperator::IsNull->label())->toBe('Is Null');
    });

    it('returns symbols', function (): void {
        expect(ConditionOperator::Equals->symbol())->toBe('=');
        expect(ConditionOperator::NotEquals->symbol())->toBe('≠');
        expect(ConditionOperator::GreaterThan->symbol())->toBe('>');
        expect(ConditionOperator::LessThan->symbol())->toBe('<');
    });

    it('determines if requires value', function (): void {
        expect(ConditionOperator::Equals->requiresValue())->toBeTrue();
        expect(ConditionOperator::Contains->requiresValue())->toBeTrue();
        expect(ConditionOperator::IsNull->requiresValue())->toBeFalse();
        expect(ConditionOperator::IsNotNull->requiresValue())->toBeFalse();
        expect(ConditionOperator::IsTrue->requiresValue())->toBeFalse();
        expect(ConditionOperator::IsFalse->requiresValue())->toBeFalse();
    });

    it('determines if is array operator', function (): void {
        expect(ConditionOperator::In->isArrayOperator())->toBeTrue();
        expect(ConditionOperator::NotIn->isArrayOperator())->toBeTrue();
        expect(ConditionOperator::ContainsAny->isArrayOperator())->toBeTrue();
        expect(ConditionOperator::ContainsAll->isArrayOperator())->toBeTrue();
        expect(ConditionOperator::Between->isArrayOperator())->toBeTrue();
        expect(ConditionOperator::Equals->isArrayOperator())->toBeFalse();
    });

    it('determines if is string operator', function (): void {
        expect(ConditionOperator::Contains->isStringOperator())->toBeTrue();
        expect(ConditionOperator::NotContains->isStringOperator())->toBeTrue();
        expect(ConditionOperator::StartsWith->isStringOperator())->toBeTrue();
        expect(ConditionOperator::EndsWith->isStringOperator())->toBeTrue();
        expect(ConditionOperator::Matches->isStringOperator())->toBeTrue();
        expect(ConditionOperator::Equals->isStringOperator())->toBeFalse();
    });

    it('determines if is date operator', function (): void {
        expect(ConditionOperator::Before->isDateOperator())->toBeTrue();
        expect(ConditionOperator::After->isDateOperator())->toBeTrue();
        expect(ConditionOperator::Between->isDateOperator())->toBeTrue();
        expect(ConditionOperator::Equals->isDateOperator())->toBeFalse();
    });

    it('determines if is comparison operator', function (): void {
        expect(ConditionOperator::Equals->isComparisonOperator())->toBeTrue();
        expect(ConditionOperator::NotEquals->isComparisonOperator())->toBeTrue();
        expect(ConditionOperator::GreaterThan->isComparisonOperator())->toBeTrue();
        expect(ConditionOperator::LessThan->isComparisonOperator())->toBeTrue();
        expect(ConditionOperator::Contains->isComparisonOperator())->toBeFalse();
    });

    it('evaluates equality operators correctly', function (): void {
        expect(ConditionOperator::Equals->evaluate('test', 'test'))->toBeTrue();
        expect(ConditionOperator::Equals->evaluate('test', 'other'))->toBeFalse();
        expect(ConditionOperator::NotEquals->evaluate('test', 'other'))->toBeTrue();
        expect(ConditionOperator::NotEquals->evaluate('test', 'test'))->toBeFalse();
    });

    it('evaluates comparison operators correctly', function (): void {
        expect(ConditionOperator::GreaterThan->evaluate(10, 5))->toBeTrue();
        expect(ConditionOperator::GreaterThan->evaluate(5, 10))->toBeFalse();
        expect(ConditionOperator::GreaterThanOrEquals->evaluate(10, 10))->toBeTrue();
        expect(ConditionOperator::LessThan->evaluate(5, 10))->toBeTrue();
        expect(ConditionOperator::LessThanOrEquals->evaluate(10, 10))->toBeTrue();
    });

    it('evaluates string operators correctly', function (): void {
        expect(ConditionOperator::Contains->evaluate('hello world', 'world'))->toBeTrue();
        expect(ConditionOperator::Contains->evaluate('hello world', 'xyz'))->toBeFalse();
        expect(ConditionOperator::NotContains->evaluate('hello world', 'xyz'))->toBeTrue();
        expect(ConditionOperator::StartsWith->evaluate('hello world', 'hello'))->toBeTrue();
        expect(ConditionOperator::EndsWith->evaluate('hello world', 'world'))->toBeTrue();
        expect(ConditionOperator::Matches->evaluate('test123', '/^test\\d+$/'))->toBeTrue();
    });

    it('evaluates collection operators correctly', function (): void {
        expect(ConditionOperator::In->evaluate('a', ['a', 'b', 'c']))->toBeTrue();
        expect(ConditionOperator::In->evaluate('x', ['a', 'b', 'c']))->toBeFalse();
        expect(ConditionOperator::NotIn->evaluate('x', ['a', 'b', 'c']))->toBeTrue();
        expect(ConditionOperator::ContainsAny->evaluate(['a', 'b'], ['b', 'c']))->toBeTrue();
        expect(ConditionOperator::ContainsAll->evaluate(['a', 'b', 'c'], ['a', 'b']))->toBeTrue();
    });

    it('evaluates null check operators correctly', function (): void {
        expect(ConditionOperator::IsNull->evaluate(null))->toBeTrue();
        expect(ConditionOperator::IsNull->evaluate('value'))->toBeFalse();
        expect(ConditionOperator::IsNotNull->evaluate('value'))->toBeTrue();
        expect(ConditionOperator::IsNotNull->evaluate(null))->toBeFalse();
    });

    it('evaluates boolean operators correctly', function (): void {
        expect(ConditionOperator::IsTrue->evaluate(true))->toBeTrue();
        expect(ConditionOperator::IsTrue->evaluate(false))->toBeFalse();
        expect(ConditionOperator::IsFalse->evaluate(false))->toBeTrue();
        expect(ConditionOperator::IsFalse->evaluate(true))->toBeFalse();
    });

    it('evaluates date operators correctly', function (): void {
        expect(ConditionOperator::Before->evaluate(5, 10))->toBeTrue();
        expect(ConditionOperator::After->evaluate(10, 5))->toBeTrue();
        expect(ConditionOperator::Between->evaluate(5, [1, 10]))->toBeTrue();
        expect(ConditionOperator::Between->evaluate(15, [1, 10]))->toBeFalse();
    });
});

describe('ImpactLevel enum', function (): void {
    it('has all expected cases', function (): void {
        expect(ImpactLevel::cases())->toHaveCount(5);
        expect(ImpactLevel::None->value)->toBe('none');
        expect(ImpactLevel::Low->value)->toBe('low');
        expect(ImpactLevel::Medium->value)->toBe('medium');
        expect(ImpactLevel::High->value)->toBe('high');
        expect(ImpactLevel::Critical->value)->toBe('critical');
    });

    it('calculates from affected users count', function (): void {
        expect(ImpactLevel::fromAffectedUsers(0))->toBe(ImpactLevel::None);
        expect(ImpactLevel::fromAffectedUsers(5))->toBe(ImpactLevel::Low);
        expect(ImpactLevel::fromAffectedUsers(50))->toBe(ImpactLevel::Medium);
        expect(ImpactLevel::fromAffectedUsers(500))->toBe(ImpactLevel::High);
        expect(ImpactLevel::fromAffectedUsers(5000))->toBe(ImpactLevel::Critical);
    });

    it('calculates from percentage when total users provided', function (): void {
        expect(ImpactLevel::fromAffectedUsers(4, 100))->toBe(ImpactLevel::None);
        expect(ImpactLevel::fromAffectedUsers(10, 100))->toBe(ImpactLevel::Low);
        expect(ImpactLevel::fromAffectedUsers(30, 100))->toBe(ImpactLevel::Medium);
        expect(ImpactLevel::fromAffectedUsers(60, 100))->toBe(ImpactLevel::High);
        expect(ImpactLevel::fromAffectedUsers(80, 100))->toBe(ImpactLevel::Critical);
    });

    it('returns labels', function (): void {
        expect(ImpactLevel::None->label())->toBe('No Impact');
        expect(ImpactLevel::Critical->label())->toBe('Critical Impact');
    });

    it('returns descriptions', function (): void {
        foreach (ImpactLevel::cases() as $level) {
            expect($level->description())->toBeString()->not->toBeEmpty();
        }
    });

    it('returns colors', function (): void {
        expect(ImpactLevel::None->color())->toBe('gray');
        expect(ImpactLevel::Low->color())->toBe('success');
        expect(ImpactLevel::Medium->color())->toBe('info');
        expect(ImpactLevel::High->color())->toBe('warning');
        expect(ImpactLevel::Critical->color())->toBe('danger');
    });

    it('returns icons', function (): void {
        foreach (ImpactLevel::cases() as $level) {
            expect($level->icon())->toBeString()->toStartWith('heroicon-o-');
        }
    });

    it('returns numeric levels', function (): void {
        expect(ImpactLevel::None->numericLevel())->toBe(0);
        expect(ImpactLevel::Low->numericLevel())->toBe(1);
        expect(ImpactLevel::Medium->numericLevel())->toBe(2);
        expect(ImpactLevel::High->numericLevel())->toBe(3);
        expect(ImpactLevel::Critical->numericLevel())->toBe(4);
    });

    it('determines if requires approval', function (): void {
        expect(ImpactLevel::None->requiresApproval())->toBeFalse();
        expect(ImpactLevel::Low->requiresApproval())->toBeFalse();
        expect(ImpactLevel::Medium->requiresApproval())->toBeFalse();
        expect(ImpactLevel::High->requiresApproval())->toBeTrue();
        expect(ImpactLevel::Critical->requiresApproval())->toBeTrue();
    });

    it('determines if requires confirmation', function (): void {
        expect(ImpactLevel::None->requiresConfirmation())->toBeFalse();
        expect(ImpactLevel::Low->requiresConfirmation())->toBeFalse();
        expect(ImpactLevel::Medium->requiresConfirmation())->toBeTrue();
        expect(ImpactLevel::High->requiresConfirmation())->toBeTrue();
        expect(ImpactLevel::Critical->requiresConfirmation())->toBeTrue();
    });
});

describe('PermissionScope enum', function (): void {
    it('has all expected cases', function (): void {
        expect(PermissionScope::cases())->toHaveCount(6);
        expect(PermissionScope::Global->value)->toBe('global');
        expect(PermissionScope::Team->value)->toBe('team');
        expect(PermissionScope::Tenant->value)->toBe('tenant');
        expect(PermissionScope::Resource->value)->toBe('resource');
        expect(PermissionScope::Temporal->value)->toBe('temporal');
        expect(PermissionScope::Owner->value)->toBe('owner');
    });

    it('returns labels', function (): void {
        foreach (PermissionScope::cases() as $scope) {
            expect($scope->label())->toBeString()->not->toBeEmpty();
        }
    });

    it('returns descriptions', function (): void {
        foreach (PermissionScope::cases() as $scope) {
            expect($scope->description())->toBeString()->not->toBeEmpty();
        }
    });

    it('returns icons', function (): void {
        foreach (PermissionScope::cases() as $scope) {
            expect($scope->icon())->toBeString()->toStartWith('heroicon-o-');
        }
    });

    it('returns colors', function (): void {
        foreach (PermissionScope::cases() as $scope) {
            expect($scope->color())->toBeString()->not->toBeEmpty();
        }
    });

    it('determines if requires scope id', function (): void {
        expect(PermissionScope::Global->requiresScopeId())->toBeFalse();
        expect(PermissionScope::Owner->requiresScopeId())->toBeFalse();
        expect(PermissionScope::Team->requiresScopeId())->toBeTrue();
        expect(PermissionScope::Tenant->requiresScopeId())->toBeTrue();
        expect(PermissionScope::Resource->requiresScopeId())->toBeTrue();
        expect(PermissionScope::Temporal->requiresScopeId())->toBeTrue();
    });

    it('determines if supports expiration', function (): void {
        expect(PermissionScope::Temporal->supportsExpiration())->toBeTrue();
        expect(PermissionScope::Global->supportsExpiration())->toBeFalse();
        expect(PermissionScope::Team->supportsExpiration())->toBeFalse();
    });
});

describe('PolicyDecision enum', function (): void {
    it('has all expected cases', function (): void {
        expect(PolicyDecision::cases())->toHaveCount(4);
        expect(PolicyDecision::Permit->value)->toBe('permit');
        expect(PolicyDecision::Deny->value)->toBe('deny');
        expect(PolicyDecision::NotApplicable->value)->toBe('not_applicable');
        expect(PolicyDecision::Indeterminate->value)->toBe('indeterminate');
    });

    it('returns labels', function (): void {
        expect(PolicyDecision::Permit->label())->toBe('Permit');
        expect(PolicyDecision::Deny->label())->toBe('Deny');
        expect(PolicyDecision::NotApplicable->label())->toBe('Not Applicable');
        expect(PolicyDecision::Indeterminate->label())->toBe('Indeterminate');
    });

    it('returns descriptions', function (): void {
        foreach (PolicyDecision::cases() as $decision) {
            expect($decision->description())->toBeString()->not->toBeEmpty();
        }
    });

    it('returns colors', function (): void {
        expect(PolicyDecision::Permit->color())->toBe('success');
        expect(PolicyDecision::Deny->color())->toBe('danger');
        expect(PolicyDecision::NotApplicable->color())->toBe('gray');
        expect(PolicyDecision::Indeterminate->color())->toBe('warning');
    });

    it('returns icons', function (): void {
        foreach (PolicyDecision::cases() as $decision) {
            expect($decision->icon())->toBeString()->toStartWith('heroicon-o-');
        }
    });

    it('determines if access is granted', function (): void {
        expect(PolicyDecision::Permit->isAccessGranted())->toBeTrue();
        expect(PolicyDecision::Deny->isAccessGranted())->toBeFalse();
        expect(PolicyDecision::NotApplicable->isAccessGranted())->toBeFalse();
        expect(PolicyDecision::Indeterminate->isAccessGranted())->toBeFalse();
    });

    it('determines if access is denied', function (): void {
        expect(PolicyDecision::Deny->isAccessDenied())->toBeTrue();
        expect(PolicyDecision::Permit->isAccessDenied())->toBeFalse();
    });

    it('determines if is conclusive', function (): void {
        expect(PolicyDecision::Permit->isConclusive())->toBeTrue();
        expect(PolicyDecision::Deny->isConclusive())->toBeTrue();
        expect(PolicyDecision::NotApplicable->isConclusive())->toBeFalse();
        expect(PolicyDecision::Indeterminate->isConclusive())->toBeFalse();
    });

    it('determines if requires fallback', function (): void {
        expect(PolicyDecision::NotApplicable->requiresFallback())->toBeTrue();
        expect(PolicyDecision::Indeterminate->requiresFallback())->toBeTrue();
        expect(PolicyDecision::Permit->requiresFallback())->toBeFalse();
        expect(PolicyDecision::Deny->requiresFallback())->toBeFalse();
    });
});

describe('PolicyEffect enum', function (): void {
    it('has all expected cases', function (): void {
        expect(PolicyEffect::cases())->toHaveCount(2);
        expect(PolicyEffect::Allow->value)->toBe('allow');
        expect(PolicyEffect::Deny->value)->toBe('deny');
    });

    it('returns labels', function (): void {
        expect(PolicyEffect::Allow->label())->toBe('Allow');
        expect(PolicyEffect::Deny->label())->toBe('Deny');
    });

    it('returns descriptions', function (): void {
        foreach (PolicyEffect::cases() as $effect) {
            expect($effect->description())->toBeString()->not->toBeEmpty();
        }
    });

    it('returns colors', function (): void {
        expect(PolicyEffect::Allow->color())->toBe('success');
        expect(PolicyEffect::Deny->color())->toBe('danger');
    });

    it('returns icons', function (): void {
        foreach (PolicyEffect::cases() as $effect) {
            expect($effect->icon())->toBeString()->toStartWith('heroicon-o-');
        }
    });

    it('determines if is permissive', function (): void {
        expect(PolicyEffect::Allow->isPermissive())->toBeTrue();
        expect(PolicyEffect::Deny->isPermissive())->toBeFalse();
    });

    it('determines if is restrictive', function (): void {
        expect(PolicyEffect::Deny->isRestrictive())->toBeTrue();
        expect(PolicyEffect::Allow->isRestrictive())->toBeFalse();
    });
});

describe('PolicyCombiningAlgorithm enum', function (): void {
    it('has all expected cases', function (): void {
        expect(PolicyCombiningAlgorithm::cases())->toHaveCount(6);
        expect(PolicyCombiningAlgorithm::DenyOverrides->value)->toBe('deny_overrides');
        expect(PolicyCombiningAlgorithm::PermitOverrides->value)->toBe('permit_overrides');
        expect(PolicyCombiningAlgorithm::FirstApplicable->value)->toBe('first_applicable');
        expect(PolicyCombiningAlgorithm::OnlyOneApplicable->value)->toBe('only_one_applicable');
        expect(PolicyCombiningAlgorithm::PermitUnlessDeny->value)->toBe('permit_unless_deny');
        expect(PolicyCombiningAlgorithm::DenyUnlessPermit->value)->toBe('deny_unless_permit');
    });

    it('returns labels', function (): void {
        foreach (PolicyCombiningAlgorithm::cases() as $algo) {
            expect($algo->label())->toBeString()->not->toBeEmpty();
        }
    });

    it('returns descriptions', function (): void {
        foreach (PolicyCombiningAlgorithm::cases() as $algo) {
            expect($algo->description())->toBeString()->not->toBeEmpty();
        }
    });

    it('returns default decisions', function (): void {
        expect(PolicyCombiningAlgorithm::DenyOverrides->defaultDecision())->toBe(PolicyDecision::Deny);
        expect(PolicyCombiningAlgorithm::DenyUnlessPermit->defaultDecision())->toBe(PolicyDecision::Deny);
        expect(PolicyCombiningAlgorithm::OnlyOneApplicable->defaultDecision())->toBe(PolicyDecision::Deny);
        expect(PolicyCombiningAlgorithm::PermitOverrides->defaultDecision())->toBe(PolicyDecision::Permit);
        expect(PolicyCombiningAlgorithm::PermitUnlessDeny->defaultDecision())->toBe(PolicyDecision::Permit);
        expect(PolicyCombiningAlgorithm::FirstApplicable->defaultDecision())->toBe(PolicyDecision::NotApplicable);
    });

    it('combines with deny overrides', function (): void {
        $algo = PolicyCombiningAlgorithm::DenyOverrides;

        expect($algo->combine([PolicyDecision::Permit, PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([PolicyDecision::Permit, PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::NotApplicable]))->toBe(PolicyDecision::NotApplicable);
        expect($algo->combine([PolicyDecision::Indeterminate]))->toBe(PolicyDecision::Indeterminate);
        expect($algo->combine([]))->toBe(PolicyDecision::Deny);
    });

    it('combines with permit overrides', function (): void {
        $algo = PolicyCombiningAlgorithm::PermitOverrides;

        expect($algo->combine([PolicyDecision::Deny, PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::Deny, PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([PolicyDecision::NotApplicable]))->toBe(PolicyDecision::NotApplicable);
        expect($algo->combine([PolicyDecision::Indeterminate]))->toBe(PolicyDecision::Indeterminate);
        expect($algo->combine([]))->toBe(PolicyDecision::Permit);
    });

    it('combines with first applicable', function (): void {
        $algo = PolicyCombiningAlgorithm::FirstApplicable;

        expect($algo->combine([PolicyDecision::NotApplicable, PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::Deny, PolicyDecision::Permit]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([PolicyDecision::NotApplicable, PolicyDecision::NotApplicable]))->toBe(PolicyDecision::NotApplicable);
        expect($algo->combine([]))->toBe(PolicyDecision::NotApplicable);
    });

    it('combines with only one applicable', function (): void {
        $algo = PolicyCombiningAlgorithm::OnlyOneApplicable;

        expect($algo->combine([PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([PolicyDecision::Permit, PolicyDecision::Deny]))->toBe(PolicyDecision::Indeterminate);
        expect($algo->combine([PolicyDecision::NotApplicable]))->toBe(PolicyDecision::NotApplicable);
        expect($algo->combine([]))->toBe(PolicyDecision::Deny);
    });

    it('combines with permit unless deny', function (): void {
        $algo = PolicyCombiningAlgorithm::PermitUnlessDeny;

        expect($algo->combine([PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::NotApplicable]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([]))->toBe(PolicyDecision::Permit);
    });

    it('combines with deny unless permit', function (): void {
        $algo = PolicyCombiningAlgorithm::DenyUnlessPermit;

        expect($algo->combine([PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([PolicyDecision::NotApplicable]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([]))->toBe(PolicyDecision::Deny);
    });
});
