<?php
namespace Apie\Tests\RestApi\EventListeners;

use Apie\Common\Actions\CreateObjectAction;
use Apie\Common\Actions\GetItemAction;
use Apie\Common\Actions\ModifyObjectAction;
use Apie\Common\Actions\RunItemMethodAction;
use Apie\Common\Events\ApieResponseCreated;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Context\ApieContext;
use Apie\Core\ContextConstants;
use Apie\Core\Datalayers\ApieDatalayer;
use Apie\Core\Datalayers\DataLayerMetadata;
use Apie\Core\Entities\EntityInterface;
use Apie\Fixtures\Entities\UserWithAutoincrementKey;
use Apie\Fixtures\Identifiers\UserAutoincrementIdentifier;
use Apie\RestApi\EventListeners\AddEntityCacheHeadersEventSubscriber;
use DateTimeImmutable;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class AddEntityCacheHeadersEventSubscriberTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_revalidating_cache_headers_for_a_single_resource_get(): void
    {
        $metadata = new DataLayerMetadata(
            new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2025-02-01T12:34:56.123456+00:00'),
            '1.0.0-RC2',
            '1.0.0-RC3'
        );
        $dataLayer = $this->createMock(ApieDatalayer::class);
        $dataLayer->expects($this->once())
            ->method('getMetadata')
            ->with(
                $this->callback(static fn (UserAutoincrementIdentifier $identifier): bool => $identifier->toNative() === 42),
                $this->callback(static fn (BoundedContextId $boundedContextId): bool => $boundedContextId->toNative() === 'default')
            )
            ->willReturn($metadata);
        $context = new ApieContext([
            ContextConstants::REST_API => true,
            ContextConstants::APIE_ACTION => GetItemAction::class,
            ContextConstants::RESOURCE_NAME => UserWithAutoincrementKey::class,
            ContextConstants::RESOURCE_ID => '42',
            ContextConstants::BOUNDED_CONTEXT_ID => 'default',
            ApieDatalayer::class => $dataLayer,
        ]);
        $event = new ApieResponseCreated(new Response(), $context);

        (new AddEntityCacheHeadersEventSubscriber())->onApieResponseCreated($event);

        $expectedTag = hash('sha256', '1.0.0-RC3:1738413296.123456');
        $this->assertSame(['"' . $expectedTag . '"'], $event->response->getHeader('ETag'));
        $this->assertSame(['no-cache'], $event->response->getHeader('Cache-Control'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_preserves_an_etag_already_set_on_the_response(): void
    {
        $dataLayer = $this->createMock(ApieDatalayer::class);
        $dataLayer->expects($this->never())->method('getMetadata');
        $context = new ApieContext([
            ContextConstants::REST_API => true,
            ContextConstants::APIE_ACTION => GetItemAction::class,
            ApieDatalayer::class => $dataLayer,
        ]);
        $event = new ApieResponseCreated(
            (new Response())->withHeader('ETag', '"provided-etag"'),
            $context
        );

        (new AddEntityCacheHeadersEventSubscriber())->onApieResponseCreated($event);

        $this->assertSame(['"provided-etag"'], $event->response->getHeader('ETag'));
        $this->assertSame(['no-cache'], $event->response->getHeader('Cache-Control'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_add_entity_cache_headers_to_other_actions(): void
    {
        $dataLayer = $this->createMock(ApieDatalayer::class);
        $dataLayer->expects($this->never())->method('getMetadata');
        $context = new ApieContext([
            ContextConstants::REST_API => true,
            ContextConstants::RESOURCE_NAME => UserWithAutoincrementKey::class,
            ApieDatalayer::class => $dataLayer,
        ]);
        $event = new ApieResponseCreated(new Response(), $context);

        (new AddEntityCacheHeadersEventSubscriber())->onApieResponseCreated($event);

        $this->assertFalse($event->response->hasHeader('ETag'));
        $this->assertFalse($event->response->hasHeader('Cache-Control'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_etag_when_the_resource_name_is_not_an_entity_class(): void
    {
        $dataLayer = $this->createMock(ApieDatalayer::class);
        $dataLayer->expects($this->never())->method('getMetadata');
        $context = new ApieContext([
            ContextConstants::REST_API => true,
            ContextConstants::APIE_ACTION => GetItemAction::class,
            ContextConstants::RESOURCE_NAME => 'not-an-entity-class',
            ContextConstants::RESOURCE_ID => '42',
            ApieDatalayer::class => $dataLayer,
        ]);
        $event = new ApieResponseCreated(new Response(), $context);

        (new AddEntityCacheHeadersEventSubscriber())->onApieResponseCreated($event);

        $this->assertFalse($event->response->hasHeader('ETag'));
        $this->assertFalse($event->response->hasHeader('Cache-Control'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_leaves_conditional_response_decisions_to_the_action(): void
    {
        $metadata = new DataLayerMetadata(
            new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2025-02-01T12:34:56.123456+00:00'),
            '1.0.0-RC2',
            '1.0.0-RC3'
        );
        $dataLayer = $this->createMock(ApieDatalayer::class);
        $dataLayer->expects($this->once())
            ->method('getMetadata')
            ->willReturn($metadata);
        $etag = '"' . $metadata->etag . '"';
        $request = (new ServerRequest('GET', '/users/42'))
                ->withHeader('If-None-Match', '"older-etag", W/' . $etag);
        $event = new ApieResponseCreated(
            new Response(200, [], 'response body'),
            $this->createItemContext($dataLayer, $request)
        );

        (new AddEntityCacheHeadersEventSubscriber())->onApieResponseCreated($event);

        $this->assertSame(200, $event->response->getStatusCode());
        $this->assertSame('response body', (string) $event->response->getBody());
        $this->assertSame([$etag], $event->response->getHeader('ETag'));
        $this->assertSame(['no-cache'], $event->response->getHeader('Cache-Control'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_keeps_the_response_for_a_non_matching_if_none_match(): void
    {
        $dataLayer = $this->createMock(ApieDatalayer::class);
        $dataLayer->expects($this->once())
            ->method('getMetadata')
            ->willReturn(new DataLayerMetadata(
                new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
                new DateTimeImmutable('2025-02-01T12:34:56.123456+00:00'),
                '1.0.0-RC2',
                '1.0.0-RC3'
            ));
        $request = (new ServerRequest('GET', '/users/42'))
            ->withHeader('If-None-Match', '"some-other-etag"');
        $event = new ApieResponseCreated(
            new Response(200, [], 'response body'),
            $this->createItemContext($dataLayer, $request)
        );

        (new AddEntityCacheHeadersEventSubscriber())->onApieResponseCreated($event);

        $this->assertSame(200, $event->response->getStatusCode());
        $this->assertSame('response body', (string) $event->response->getBody());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_revalidating_cache_headers_to_a_successful_patch_response(): void
    {
        $metadata = new DataLayerMetadata(
            new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2025-02-01T12:34:56.123456+00:00'),
            '1.0.0-RC2',
            '1.0.0-RC3'
        );
        $dataLayer = $this->createMock(ApieDatalayer::class);
        $dataLayer->expects($this->once())
            ->method('getMetadata')
            ->willReturn($metadata);
        $context = $this->createItemContext(
            $dataLayer,
            new ServerRequest('PATCH', '/users/42'),
            ModifyObjectAction::class
        );
        $event = new ApieResponseCreated(new Response(200, [], 'updated resource'), $context);

        (new AddEntityCacheHeadersEventSubscriber())->onApieResponseCreated($event);

        $this->assertSame(['"' . $metadata->etag . '"'], $event->response->getHeader('ETag'));
        $this->assertSame(['no-cache'], $event->response->getHeader('Cache-Control'));
        $this->assertSame(200, $event->response->getStatusCode());
        $this->assertSame('updated resource', (string) $event->response->getBody());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_etag_headers_to_other_successful_resource_responses(): void
    {
        $metadata = new DataLayerMetadata(
            new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2025-02-01T12:34:56.123456+00:00'),
            '1.0.0-RC2',
            '1.0.0-RC3'
        );
        $dataLayer = $this->createMock(ApieDatalayer::class);
        $dataLayer->expects($this->once())
            ->method('getMetadata')
            ->willReturn($metadata);
        $context = $this->createItemContext(
            $dataLayer,
            new ServerRequest('GET', '/users/42/action'),
            RunItemMethodAction::class
        );
        $event = new ApieResponseCreated(new Response(200), $context);

        (new AddEntityCacheHeadersEventSubscriber())->onApieResponseCreated($event);

        $this->assertSame(['"' . $metadata->etag . '"'], $event->response->getHeader('ETag'));
        $this->assertSame(['no-cache'], $event->response->getHeader('Cache-Control'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_etag_headers_to_a_created_resource_response(): void
    {
        $metadata = new DataLayerMetadata(
            new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2025-02-01T12:34:56.123456+00:00'),
            '1.0.0-RC2',
            '1.0.0-RC3'
        );
        $dataLayer = $this->createMock(ApieDatalayer::class);
        $dataLayer->expects($this->once())
            ->method('getMetadata')
            ->with(
                $this->callback(static fn (UserAutoincrementIdentifier $identifier): bool => $identifier->toNative() === 42),
                $this->anything()
            )
            ->willReturn($metadata);
        $resource = $this->createMock(EntityInterface::class);
        $resource->method('getId')->willReturn(UserAutoincrementIdentifier::fromNative(42));
        $context = $this->createItemContext(
            $dataLayer,
            new ServerRequest('POST', '/users'),
            CreateObjectAction::class
        )->withContext(ContextConstants::RESOURCE, $resource);
        $event = new ApieResponseCreated(new Response(201), $context);

        (new AddEntityCacheHeadersEventSubscriber())->onApieResponseCreated($event);

        $this->assertSame(['"' . $metadata->etag . '"'], $event->response->getHeader('ETag'));
        $this->assertSame(['no-cache'], $event->response->getHeader('Cache-Control'));
        $this->assertSame(201, $event->response->getStatusCode());
    }

    private function createItemContext(
        ApieDatalayer $dataLayer,
        ServerRequest $request,
        string $action = GetItemAction::class
    ): ApieContext {
        return new ApieContext([
            ContextConstants::REST_API => true,
            ContextConstants::APIE_ACTION => $action,
            ContextConstants::RESOURCE_NAME => UserWithAutoincrementKey::class,
            ContextConstants::RESOURCE_ID => '42',
            ContextConstants::BOUNDED_CONTEXT_ID => 'default',
            ApieDatalayer::class => $dataLayer,
            \Psr\Http\Message\ServerRequestInterface::class => $request,
        ]);
    }
}
