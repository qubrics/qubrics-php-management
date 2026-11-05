<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Composer\InstalledVersions;
use Qubrics\Management\ClientFactory;
use Qubrics\Management\Configuration;
use Qubrics\Management\Model\Connection;
use Qubrics\Management\Model\ConnectionResponse;
use Qubrics\Management\ObjectSerializer;

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

expect(InstalledVersions::isInstalled('qubrics/management'), 'Composer must install qubrics/management');
$api = ClientFactory::createConnectionApi('smx_root_example');
$request = $api->managementGetConnectionRequest();
expect((string) $request->getUri() === 'https://app.qubrics.com/api/v1/me', 'Connection request uses the Qubrics host');
expect($request->getHeaderLine('Authorization') === 'Bearer smx_root_example', 'Root key authentication remains compatible');
expect($request->getHeaderLine('User-Agent') === 'OpenAPI-Generator/3.0.0/PHP', 'User agent identifies the package version');
expect((new Configuration())->getHostSettings()[0]['url'] === 'https://app.qubrics.com/api/v1', 'Server defaults use the Qubrics host');

$custom = ClientFactory::createConnectionApi('smx_root_example', 'https://api.example.test/api/v1');
expect($custom->managementGetConnectionRequest()->getUri()->getHost() === 'api.example.test', 'Explicit hosts remain supported');
$oauth = ClientFactory::createConnectionApiWithAccessToken('oauth-token');
expect($oauth->getConfig()->getHost() === 'https://app.qubrics.com/api/v1', 'OAuth factories use the Qubrics host');

try {
    ClientFactory::createConnectionApi('smx_mbx_example');
    throw new RuntimeException('Mailbox keys must not create Management clients');
} catch (InvalidArgumentException $error) {
    expect(str_contains($error->getMessage(), 'root'), 'Wrong-surface keys are rejected');
}

$response = ObjectSerializer::deserialize((object) ['data' => (object) []], ConnectionResponse::class);
expect($response instanceof ConnectionResponse && $response->getData() instanceof Connection, 'Nested generated model names autoload');

echo "Qubrics Management public contract passed\n";
