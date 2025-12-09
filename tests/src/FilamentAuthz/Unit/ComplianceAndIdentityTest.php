<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\ComplianceReportGenerator;
use AIArmada\FilamentAuthz\Services\IdentityProviderSync;

test('compliance report generator can be instantiated', function (): void {
    $generator = new ComplianceReportGenerator();

    expect($generator)->toBeInstanceOf(ComplianceReportGenerator::class);
});

test('compliance report generator generates soc2 report structure', function (): void {
    $generator = new ComplianceReportGenerator();
    $report = $generator->generateSoc2Report();

    expect($report)
        ->toBeArray()
        ->toHaveKey('title')
        ->toHaveKey('generated_at')
        ->toHaveKey('period')
        ->toHaveKey('sections');

    expect($report['sections'])
        ->toHaveKey('user_access')
        ->toHaveKey('role_distribution')
        ->toHaveKey('privileged_access')
        ->toHaveKey('permission_summary');
});

test('compliance report generator generates segregation of duties report', function (): void {
    $generator = new ComplianceReportGenerator();
    $report = $generator->generateSegregationOfDutiesReport();

    expect($report)
        ->toBeArray()
        ->toHaveKey('title')
        ->toHaveKey('generated_at')
        ->toHaveKey('violations')
        ->toHaveKey('status')
        ->toHaveKey('score');
});

test('compliance report generator generates gdpr report', function (): void {
    $generator = new ComplianceReportGenerator();
    $report = $generator->generateGdprReport();

    expect($report)
        ->toBeArray()
        ->toHaveKey('title')
        ->toHaveKey('generated_at')
        ->toHaveKey('pii_permissions')
        ->toHaveKey('recommendations');
});

test('compliance report generator calculates score', function (): void {
    $generator = new ComplianceReportGenerator();
    $score = $generator->calculateComplianceScore();

    expect($score)
        ->toBeArray()
        ->toHaveKey('overall_score')
        ->toHaveKey('grade')
        ->toHaveKey('breakdown')
        ->toHaveKey('recommendations');

    expect($score['grade'])->toBeIn(['A', 'B', 'C', 'D', 'F']);
    expect($score['overall_score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

test('compliance report generator exports to json', function (): void {
    $generator = new ComplianceReportGenerator();

    $json = $generator->exportToJson('soc2');
    expect($json)->toBeJson();

    $json = $generator->exportToJson('sod');
    expect($json)->toBeJson();

    $json = $generator->exportToJson('gdpr');
    expect($json)->toBeJson();

    $json = $generator->exportToJson('score');
    expect($json)->toBeJson();
});

test('identity provider sync can be instantiated', function (): void {
    $sync = new IdentityProviderSync();

    expect($sync)->toBeInstanceOf(IdentityProviderSync::class);
});

test('identity provider sync can set provider type', function (): void {
    $sync = new IdentityProviderSync();
    $result = $sync->setProviderType('saml');

    expect($result)->toBeInstanceOf(IdentityProviderSync::class);
});

test('identity provider sync can set provider name', function (): void {
    $sync = new IdentityProviderSync();
    $result = $sync->setProviderName('company-ad');

    expect($result)->toBeInstanceOf(IdentityProviderSync::class);
});

test('identity provider sync can set mapping', function (): void {
    $sync = new IdentityProviderSync();
    $result = $sync->setMapping([
        'Administrators' => 'admin',
        'Users' => 'user',
    ]);

    expect($result)->toBeInstanceOf(IdentityProviderSync::class);
});

test('identity provider sync parses ldap groups', function (): void {
    $sync = new IdentityProviderSync();

    $groups = $sync->parseLdapGroups([
        'memberOf' => [
            'CN=Admins,OU=Groups,DC=example,DC=com',
            'CN=Users,OU=Groups,DC=example,DC=com',
        ],
    ]);

    expect($groups)
        ->toBeArray()
        ->toContain('Admins')
        ->toContain('Users');
});

test('identity provider sync parses saml groups', function (): void {
    $sync = new IdentityProviderSync();

    $groups = $sync->parseSamlGroups([
        'groups' => ['Admin', 'Editor', 'Viewer'],
    ]);

    expect($groups)
        ->toBeArray()
        ->toContain('Admin')
        ->toContain('Editor')
        ->toContain('Viewer');
});

test('identity provider sync handles empty ldap groups', function (): void {
    $sync = new IdentityProviderSync();

    $groups = $sync->parseLdapGroups([]);

    expect($groups)->toBeArray()->toBeEmpty();
});

test('identity provider sync handles string memberof', function (): void {
    $sync = new IdentityProviderSync();

    $groups = $sync->parseLdapGroups([
        'memberOf' => 'CN=SingleGroup,OU=Groups,DC=example,DC=com',
    ]);

    expect($groups)
        ->toBeArray()
        ->toContain('SingleGroup');
});
