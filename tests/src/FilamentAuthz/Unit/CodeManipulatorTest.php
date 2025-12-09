<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\CodeManipulator;

test('code manipulator can be instantiated with file', function (): void {
    $testFile = storage_path('test-class.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected string $name = 'test';
}
PHP);

    $manipulator = CodeManipulator::make($testFile);

    expect($manipulator)->toBeInstanceOf(CodeManipulator::class);

    unlink($testFile);
});

test('code manipulator can check for trait', function (): void {
    $testFile = storage_path('test-class-2.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

use App\Traits\SomeTrait;

class TestClass
{
    use SomeTrait;

    protected string $name = 'test';
}
PHP);

    $manipulator = CodeManipulator::make($testFile);

    expect($manipulator->containsTrait('SomeTrait'))->toBeTrue()
        ->and($manipulator->containsTrait('OtherTrait'))->toBeFalse();

    unlink($testFile);
});

test('code manipulator can add trait', function (): void {
    $testFile = storage_path('test-class-3.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected string $name = 'test';
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addTrait('App\\Traits\\NewTrait');

    $preview = $manipulator->preview();

    // Check for trait usage in class body (use NewTrait;)
    expect($preview)->toContain('use NewTrait;');

    unlink($testFile);
});

test('code manipulator can generate diff', function (): void {
    $testFile = storage_path('test-class-4.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    protected string $name = 'test';
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addTrait('App\\Traits\\NewTrait');

    $diff = $manipulator->diff();

    expect($diff)->toContain('+');

    unlink($testFile);
});

test('code manipulator can add use statement', function (): void {
    $testFile = storage_path('test-class-5.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addUse('App\\Services\\SomeService');

    $preview = $manipulator->preview();

    expect($preview)->toContain('use App\\Services\\SomeService;');

    unlink($testFile);
});

test('code manipulator does not duplicate use statements', function (): void {
    $testFile = storage_path('test-class-6.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

use App\Services\SomeService;

class TestClass
{
}
PHP);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addUse('App\\Services\\SomeService');

    $preview = $manipulator->preview();
    $count = substr_count($preview, 'use App\\Services\\SomeService;');

    expect($count)->toBe(1);

    unlink($testFile);
});

test('code manipulator contains method check', function (): void {
    $testFile = storage_path('test-class-7.php');
    file_put_contents($testFile, <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
    public function existingMethod()
    {
        return true;
    }
}
PHP);

    $manipulator = CodeManipulator::make($testFile);

    expect($manipulator->containsMethod('existingMethod'))->toBeTrue()
        ->and($manipulator->containsMethod('missingMethod'))->toBeFalse();

    unlink($testFile);
});

test('code manipulator can undo changes', function (): void {
    $testFile = storage_path('test-class-8.php');
    $original = <<<'PHP'
<?php

namespace App\Test;

class TestClass
{
}
PHP;
    file_put_contents($testFile, $original);

    $manipulator = CodeManipulator::make($testFile);
    $manipulator->addUse('App\\Services\\First');
    $manipulator->addUse('App\\Services\\Second');

    // Undo second addition
    $manipulator->undo();
    $preview = $manipulator->preview();

    expect($preview)->toContain('App\\Services\\First')
        ->and($preview)->not->toContain('App\\Services\\Second');

    unlink($testFile);
});
