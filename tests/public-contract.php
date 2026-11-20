<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$baseUrls = ['QUBRICS_BASE_URL' => getenv('QUBRICS_BASE_URL'), 'SENDMUX_BASE_URL' => getenv('SENDMUX_BASE_URL')];
register_shutdown_function(static function () use ($baseUrls): void {
    foreach ($baseUrls as $name => $value) {
        putenv($value === false ? $name : "$name=$value");
    }
});
foreach ($baseUrls as $name => $_) {
    putenv($name);
}

use Composer\InstalledVersions;
use Qubrics\Management\ClientFactory;
use Qubrics\Management\Configuration;
use Qubrics\Management\Model\Connection;
use Qubrics\Management\Model\ConnectionResponse;
use Qubrics\Management\Model\MailWebhookEventPayload;
use Qubrics\Management\Model\ProviderUpdateBody;
use Qubrics\Management\Model\WebhookCreateBody;
use Qubrics\Management\Model\WebhookDeliveryAttempt;
use Qubrics\Management\Model\WebhookDeliveryPayload;
use Qubrics\Management\Model\WebhookEventData;
use Qubrics\Management\Model\WebhookEventPayload;
use Qubrics\Management\Model\WebhookSubscription;
use Qubrics\Management\Model\WebhookSubscriptionWithSecret;
use Qubrics\Management\Model\WebhookUpdateBody;
use Qubrics\Management\ObjectSerializer;

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(InstalledVersions::isInstalled('qubrics/management'), 'Composer must install qubrics/management');
$api = ClientFactory::createConnectionApi('smx_root_example');
$request = $api->managementGetConnectionRequest();
$expect(
    (string) $request->getUri() === 'https://app.qubrics.com/api/v1/me',
    'Connection request uses the Qubrics host'
);
$expect(
    $request->getHeaderLine('Authorization') === 'Bearer smx_root_example',
    'Root key authentication remains compatible'
);
$expect(
    $request->getHeaderLine('User-Agent') === 'OpenAPI-Generator/3.0.0/PHP',
    'User agent identifies the package version'
);
$expect(
    (new Configuration())->getHostSettings()[0]['url'] === 'https://app.qubrics.com/api/v1',
    'Server defaults use the Qubrics host'
);

$custom = ClientFactory::createConnectionApi('smx_root_example', 'https://api.example.test/api/v1');
$expect(
    $custom->managementGetConnectionRequest()->getUri()->getHost() === 'api.example.test',
    'Explicit hosts remain supported'
);
$oauth = ClientFactory::createConnectionApiWithAccessToken('oauth-token');
$expect($oauth->getConfig()->getHost() === 'https://app.qubrics.com/api/v1', 'OAuth factories use the Qubrics host');

try {
    ClientFactory::createConnectionApi('smx_mbx_example');
    throw new RuntimeException('Mailbox keys must not create Management clients');
} catch (InvalidArgumentException $error) {
    $expect(str_contains($error->getMessage(), 'root'), 'Wrong-surface keys are rejected');
}

$response = ObjectSerializer::deserialize((object) ['data' => (object) []], ConnectionResponse::class);
$expect(
    $response instanceof ConnectionResponse && $response->getData() instanceof Connection,
    'Nested generated model names autoload'
);

$event = ObjectSerializer::deserialize(
    (object) ['sendmux_message_id' => 'message-123', 'recipients' => []],
    WebhookEventData::class
);
$expect($event->getSendmuxMessageId() === 'message-123', 'Legacy message-ID accessor remains available');
$event->setSendmuxMessageId('message-456');
$expect(
    ObjectSerializer::sanitizeForSerialization($event)->sendmux_message_id === 'message-456',
    'Legacy message-ID setter preserves its wire key'
);

$mailEventMetadata = [
    'MESSAGE_DELIVERED' => 'message.delivered', 'MESSAGE_BOUNCED' => 'message.bounced',
    'MESSAGE_COMPLAINED' => 'message.complained', 'MESSAGE_REJECTED' => 'message.rejected',
    'MESSAGE_DELIVERY_DELAYED' => 'message.delivery_delayed', 'MESSAGE_RECEIVED' => 'message.received',
    'MESSAGE_RECEIVED_SPAM' => 'message.received.spam', 'SENDMUX_TEST' => 'sendmux.test',
    'UNKNOWN_DEFAULT_OPEN_API' => 'unknown_default_open_api',
];
foreach ([
    [WebhookDeliveryAttempt::class, 'EVENT_TYPE_', 'getEventTypeAllowableValues'],
    [WebhookDeliveryPayload::class, 'EVENT_TYPE_', 'getEventTypeAllowableValues'],
    [WebhookSubscription::class, 'EVENT_TYPES_', 'getEventTypesAllowableValues'],
    [WebhookSubscriptionWithSecret::class, 'EVENT_TYPES_', 'getEventTypesAllowableValues'],
] as [$model, $prefix, $getter]) {
    $expect(is_callable([$model, $getter]), 'Historical webhook models retain mail metadata: ' . $model);
    foreach ($mailEventMetadata as $name => $value) {
        $expect(constant($model . '::' . $prefix . $name) === $value, 'Mail event constants remain available');
        $expect(in_array($value, $model::$getter(), true), 'Mail event metadata retains existing values');
    }
    $expect(!in_array('calendar.changed', $model::$getter(), true), 'Retired calendar event is absent from metadata');
}

foreach (['CalendarWebhookEventData', 'CalendarWebhookEventPayload'] as $model) {
    $expect(!class_exists('Qubrics\\Management\\Model\\' . $model), 'Retired calendar model is absent: ' . $model);
}
foreach ([WebhookCreateBody::class, WebhookUpdateBody::class] as $model) {
    $expect(
        !in_array('calendar.changed', $model::getEventTypesAllowableValues(), true),
        'New webhook subscriptions do not offer calendar events'
    );
}
foreach ([
    'message.bounced', 'message.complained', 'message.delivered', 'message.delivery_delayed',
    'message.received', 'message.received.spam', 'message.rejected', 'sendmux.test',
] as $type) {
    $mail = ObjectSerializer::deserialize((object) [
        'type' => $type,
        'data' => (object) ['sendmux_message_id' => 'message-fixture', 'recipients' => []],
    ], WebhookEventPayload::class);
    $expect($mail instanceof MailWebhookEventPayload, 'Mail webhook discriminator retains its subtype: ' . $type);
    $expect($mail->getType() === $type, 'Mail webhook event label survives deserialisation');
    $expect($mail->getData() instanceof WebhookEventData, 'Mail webhook data remains typed');
    $expect($mail->getData()->getSendmuxMessageId() === 'message-fixture', 'Mail webhook data retains its values');
}
$historical = ['type' => 'calendar.changed', 'data' => ['calendar_id' => 'calendar-fixture'], 'extra' => ['retained' => true]];
$delivery = ObjectSerializer::deserialize((object) [
    'event_type' => 'calendar.changed', 'payload' => (object) $historical,
], WebhookDeliveryPayload::class);
$expect($delivery->getEventType() === 'calendar.changed', 'Historical delivery labels remain plain strings');
$expect($delivery->getPayload() === $historical, 'Historical delivery payloads retain opaque fields');
$expect(
    (new WebhookDeliveryAttempt(['event_type' => 'calendar.changed']))->getEventType() === 'calendar.changed',
    'Historical attempt labels remain plain strings'
);
foreach ([WebhookSubscription::class, WebhookSubscriptionWithSecret::class] as $model) {
    $subscription = new $model(['event_types' => ['calendar.changed', 'historical.fixture']]);
    $expect(
        $subscription->getEventTypes() === ['calendar.changed', 'historical.fixture'],
        'Historical subscriptions retain event labels'
    );
}

$start = new DateTime('2026-11-01T00:00:00+00:00');
$end = new DateTime('2026-11-02T00:00:00+00:00');
$usage = ClientFactory::createMailboxesApi('smx_root_example')->managementGetMailboxCostUsageRequest(
    'mailbox_fixture',
    $start,
    $end,
    'revision.fixture'
);
parse_str($usage->getUri()->getQuery(), $query);
$expect($query === [
    'start' => '2026-11-01T00:00:00+00:00',
    'end' => '2026-11-02T00:00:00+00:00',
], 'Cost usage dates encode as RFC3339 query strings');
$expect($usage->getHeaderLine('If-None-Match') === 'revision.fixture', 'Conditional query requests retain headers');

foreach ([[], ['project' => 'fixture']] as $variables) {
    $provider = new ProviderUpdateBody(['variables' => $variables]);
    $wire = json_decode(json_encode(ObjectSerializer::sanitizeForSerialization($provider), JSON_THROW_ON_ERROR));
    $expect($wire->variables instanceof stdClass, 'Provider variables encode as a JSON object, including empty maps');
    $expect((array) $wire->variables === $variables, 'Provider variables retain their values');
}
$omitted = ObjectSerializer::sanitizeForSerialization(new ProviderUpdateBody());
$expect(!property_exists($omitted, 'variables'), 'Omitted provider variables remain omitted');

echo "Qubrics Management public contract passed\n";
