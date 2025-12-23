<?php

declare(strict_types=1);

use AIArmada\Jnt\Http\JntClient;

it('generates correct signature digest', function (): void {
    $client = new JntClient(
        baseUrl: 'https://demoopenapi.jtexpress.my/webopenplatformapi',
        apiAccount: '640826271705595946',
        privateKey: '8e88c8477d4e4939859c560192fcafbc',
        config: []
    );

    $bizContent = '{"customerCode":"ITTEST0001","txlogisticId":"TEST123"}';

    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('generateDigest');

    $digest = $method->invoke($client, $bizContent);

    $expected = base64_encode(md5($bizContent . '8e88c8477d4e4939859c560192fcafbc', true));

    expect($digest)->toBeString()
        ->and($digest)->toBe($expected);
});

it('generates a digest that does not match random strings', function (): void {
    $client = new JntClient(
        baseUrl: 'https://demoopenapi.jtexpress.my/webopenplatformapi',
        apiAccount: '640826271705595946',
        privateKey: '8e88c8477d4e4939859c560192fcafbc',
        config: []
    );

    $bizContent = '{"customerCode":"ITTEST0001","txlogisticId":"TEST123"}';

    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('generateDigest');

    $digest = $method->invoke($client, $bizContent);

    expect($digest)->not->toBe('invalid_digest_string');
});
