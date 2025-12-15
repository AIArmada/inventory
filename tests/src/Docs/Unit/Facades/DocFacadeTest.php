<?php

declare(strict_types=1);

use AIArmada\Docs\Facades\Doc;
use AIArmada\Docs\Services\DocService;

test('doc facade resolves to doc service binding', function (): void {
    expect(Doc::getFacadeRoot())->toBeInstanceOf(DocService::class);
});
