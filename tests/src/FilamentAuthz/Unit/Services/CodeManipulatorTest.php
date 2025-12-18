<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\CodeManipulator;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir() . '/code_manipulator_test';
    if (! is_dir($this->tempDir)) {
        mkdir($this->tempDir, 0755, true);
    }
});

afterEach(function (): void {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }
});

describe('CodeManipulator', function (): void {
    it('can be instantiated with a file path', function (): void {
        $filePath = $this->tempDir . '/TestClass.php';
        file_put_contents($filePath, '<?php class TestClass {}');

        $manipulator = new CodeManipulator($filePath);

        expect($manipulator)->toBeInstanceOf(CodeManipulator::class);
    });

    it('can be created with make factory method', function (): void {
        $filePath = $this->tempDir . '/TestClass2.php';
        file_put_contents($filePath, '<?php class TestClass2 {}');

        $manipulator = CodeManipulator::make($filePath);

        expect($manipulator)->toBeInstanceOf(CodeManipulator::class);
    });

    it('adds use statement', function (): void {
        $filePath = $this->tempDir . '/UseTest.php';
        file_put_contents($filePath, "<?php\n\nnamespace App\\Models;\n\nclass UseTest {}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->addUse('App\\Traits\\HasRoles');
        $preview = $manipulator->preview();

        expect($preview)->toContain('use App\\Traits\\HasRoles;');
    });

    it('does not duplicate use statement', function (): void {
        $filePath = $this->tempDir . '/DupUseTest.php';
        file_put_contents($filePath, "<?php\n\nnamespace App\\Models;\n\nuse App\\Traits\\HasRoles;\n\nclass DupUseTest {}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->addUse('App\\Traits\\HasRoles');
        $preview = $manipulator->preview();

        $count = mb_substr_count($preview, 'use App\\Traits\\HasRoles;');
        expect($count)->toBe(1);
    });

    it('adds trait to class', function (): void {
        $filePath = $this->tempDir . '/TraitTest.php';
        file_put_contents($filePath, "<?php\n\nnamespace App\\Models;\n\nclass TraitTest\n{\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->addTrait('App\\Concerns\\HasPageAuthz');
        $preview = $manipulator->preview();

        expect($preview)->toContain('use HasPageAuthz;');
        expect($preview)->toContain('use App\\Concerns\\HasPageAuthz;');
    });

    it('does not duplicate trait', function (): void {
        $filePath = $this->tempDir . '/DupTraitTest.php';
        file_put_contents($filePath, "<?php\n\nnamespace App;\n\nuse App\\Concerns\\MyTrait;\n\nclass DupTraitTest\n{\n    use MyTrait;\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->addTrait('App\\Concerns\\MyTrait');
        $preview = $manipulator->preview();

        $count = mb_substr_count($preview, 'use MyTrait');
        expect($count)->toBe(1);
    });

    it('sets property value', function (): void {
        $filePath = $this->tempDir . '/PropTest.php';
        file_put_contents($filePath, "<?php\n\nclass PropTest\n{\n    protected \$name = 'old';\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->setProperty('name', 'new_value');
        $preview = $manipulator->preview();

        expect($preview)->toContain("'new_value'");
    });

    it('adds new property if not exists', function (): void {
        $filePath = $this->tempDir . '/NewPropTest.php';
        file_put_contents($filePath, "<?php\n\nclass NewPropTest\n{\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->setProperty('newProp', 'value');
        $preview = $manipulator->preview();

        expect($preview)->toContain('$newProp');
    });

    it('adds method to class', function (): void {
        $filePath = $this->tempDir . '/MethodTest.php';
        file_put_contents($filePath, "<?php\n\nclass MethodTest\n{\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->addMethod('testMethod', '        return true;');
        $preview = $manipulator->preview();

        expect($preview)->toContain('function testMethod()');
        expect($preview)->toContain('return true;');
    });

    it('does not duplicate method', function (): void {
        $filePath = $this->tempDir . '/DupMethodTest.php';
        file_put_contents($filePath, "<?php\n\nclass DupMethodTest\n{\n    public function existingMethod() {}\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->addMethod('existingMethod', '        return false;');
        $preview = $manipulator->preview();

        $count = mb_substr_count($preview, 'function existingMethod');
        expect($count)->toBe(1);
    });

    it('adds static method', function (): void {
        $filePath = $this->tempDir . '/StaticMethodTest.php';
        file_put_contents($filePath, "<?php\n\nclass StaticMethodTest\n{\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->addMethod('staticTest', '        return null;', 'public', true);
        $preview = $manipulator->preview();

        expect($preview)->toContain('public static function staticTest()');
    });

    it('appends to array property', function (): void {
        $filePath = $this->tempDir . '/ArrayTest.php';
        file_put_contents($filePath, "<?php\n\nclass ArrayTest\n{\n    protected array \$items = [];\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->appendToArray('items', 'key1', 'value1');
        $preview = $manipulator->preview();

        expect($preview)->toContain("'key1' => 'value1'");
    });

    it('generates diff', function (): void {
        $filePath = $this->tempDir . '/DiffTest.php';
        file_put_contents($filePath, "<?php\n\nclass DiffTest\n{\n    protected \$name = 'old';\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->setProperty('name', 'new');
        $diff = $manipulator->diff();

        expect($diff)->toContain('-')
            ->toContain('+');
    });

    it('can undo changes', function (): void {
        $filePath = $this->tempDir . '/UndoTest.php';
        $original = "<?php\n\nclass UndoTest\n{\n    protected \$name = 'original';\n}";
        file_put_contents($filePath, $original);

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->setProperty('name', 'first_change');
        $manipulator->setProperty('name', 'second_change');
        $manipulator->undo();
        $preview = $manipulator->preview();

        // After undo, should be back to the previous change (first_change)
        expect($preview)->toContain("'first_change'");
    });

    it('saves changes to file', function (): void {
        $filePath = $this->tempDir . '/SaveTest.php';
        file_put_contents($filePath, "<?php\n\nclass SaveTest\n{\n    protected \$value = 'old';\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->setProperty('value', 'saved');
        $result = $manipulator->save();

        expect($result)->toBeTrue();

        $content = file_get_contents($filePath);
        expect($content)->toContain("'saved'");
    });

    it('returns false when no changes to save', function (): void {
        $filePath = $this->tempDir . '/NoChangeTest.php';
        $content = "<?php\n\nclass NoChangeTest {}";
        file_put_contents($filePath, $content);

        $manipulator = CodeManipulator::make($filePath);
        $result = $manipulator->save();

        expect($result)->toBeFalse();
    });

    it('checks if class contains trait', function (): void {
        $filePath = $this->tempDir . '/ContainsTraitTest.php';
        file_put_contents($filePath, "<?php\n\nclass ContainsTraitTest\n{\n    use MyTrait;\n}");

        $manipulator = CodeManipulator::make($filePath);

        expect($manipulator->containsTrait('MyTrait'))->toBeTrue();
        expect($manipulator->containsTrait('OtherTrait'))->toBeFalse();
    });

    it('checks if class contains method', function (): void {
        $filePath = $this->tempDir . '/ContainsMethodTest.php';
        file_put_contents($filePath, "<?php\n\nclass ContainsMethodTest\n{\n    public function myMethod() {}\n}");

        $manipulator = CodeManipulator::make($filePath);

        expect($manipulator->containsMethod('myMethod'))->toBeTrue();
        expect($manipulator->containsMethod('otherMethod'))->toBeFalse();
    });

    it('checks if file contains use statement', function (): void {
        $filePath = $this->tempDir . '/ContainsUseTest.php';
        file_put_contents($filePath, "<?php\n\nuse App\\Traits\\MyTrait;\n\nclass ContainsUseTest {}");

        $manipulator = CodeManipulator::make($filePath);

        expect($manipulator->containsUse('App\\Traits\\MyTrait'))->toBeTrue();
        expect($manipulator->containsUse('App\\Other\\Class'))->toBeFalse();
    });

    it('converts values to string representation', function (): void {
        $filePath = $this->tempDir . '/ValueTest.php';
        file_put_contents($filePath, "<?php\n\nclass ValueTest\n{\n}");

        $manipulator = CodeManipulator::make($filePath);

        $manipulator->setProperty('stringVal', 'test');
        $manipulator->setProperty('boolVal', true);
        $manipulator->setProperty('nullVal', null);
        $manipulator->setProperty('intVal', 42);

        $preview = $manipulator->preview();

        expect($preview)->toContain("'test'");
        expect($preview)->toContain('true');
        expect($preview)->toContain('null');
        expect($preview)->toContain('42');
    });

    it('converts array values to string representation', function (): void {
        $filePath = $this->tempDir . '/ArrayValTest.php';
        file_put_contents($filePath, "<?php\n\nclass ArrayValTest\n{\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->setProperty('arrayVal', ['key' => 'value']);
        $preview = $manipulator->preview();

        expect($preview)->toContain("['key' => 'value']");
    });

    it('handles non-associative arrays', function (): void {
        $filePath = $this->tempDir . '/ListArrayTest.php';
        file_put_contents($filePath, "<?php\n\nclass ListArrayTest\n{\n}");

        $manipulator = CodeManipulator::make($filePath);
        $manipulator->setProperty('listVal', ['one', 'two', 'three']);
        $preview = $manipulator->preview();

        expect($preview)->toContain("'one'");
        expect($preview)->toContain("'two'");
        expect($preview)->toContain("'three'");
    });
});
