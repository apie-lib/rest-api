<?php
namespace Apie\Tests\RestApi\EventListeners;

use Apie\Common\Actions\GetListAction;
use Apie\Common\Events\ApieResponseCreated;
use Apie\Core\Context\ApieContext;
use Apie\Core\ContextConstants;
use Apie\Core\Datalayers\ApieDatalayerWithFilters;
use Apie\Core\Lists\StringSet;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\RestApi\EventListeners\AddNoVarySearchHeaderEventSubscriber;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

class AddNoVarySearchHeaderEventSubscriberTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_the_header_for_a_list_request_using_supported_filters(): void
    {
        $dataLayer = $this->createMock(ApieDatalayerWithFilters::class);
        $dataLayer->expects($this->once())
            ->method('getFilterColumns')
            ->willReturn(new StringSet(['name']));
        $dataLayer->expects($this->once())
            ->method('getOrderByColumns')
            ->willReturn(new StringSet(['name']));
        $context = new ApieContext([
            ContextConstants::REST_API => true,
            ContextConstants::APIE_ACTION => GetListAction::class,
            ContextConstants::RESOURCE_NAME => UserWithAddress::class,
            ContextConstants::BOUNDED_CONTEXT_ID => 'default',
            \Apie\Core\Datalayers\ApieDatalayer::class => $dataLayer,
        ]);
        $event = new ApieResponseCreated(new Response(), $context);

        (new AddNoVarySearchHeaderEventSubscriber())->onApieResponseCreated($event);

        $this->assertSame(
            ['params, except=("page" "items_per_page" "search" "query[name]" "order_by")'],
            $event->response->getHeader('No-Vary-Search')
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_add_the_header_to_non_list_requests(): void
    {
        $event = new ApieResponseCreated(new Response(), new ApieContext([
            ContextConstants::REST_API => true,
        ]));

        (new AddNoVarySearchHeaderEventSubscriber())->onApieResponseCreated($event);

        $this->assertFalse($event->response->hasHeader('No-Vary-Search'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_add_the_header_when_filter_columns_are_unknown(): void
    {
        $dataLayer = $this->createMock(ApieDatalayerWithFilters::class);
        $dataLayer->expects($this->once())
            ->method('getFilterColumns')
            ->willReturn(null);
        $context = new ApieContext([
            ContextConstants::REST_API => true,
            ContextConstants::APIE_ACTION => GetListAction::class,
            ContextConstants::RESOURCE_NAME => UserWithAddress::class,
            ContextConstants::BOUNDED_CONTEXT_ID => 'default',
            \Apie\Core\Datalayers\ApieDatalayer::class => $dataLayer,
        ]);
        $event = new ApieResponseCreated(new Response(), $context);

        (new AddNoVarySearchHeaderEventSubscriber())->onApieResponseCreated($event);

        $this->assertFalse($event->response->hasHeader('No-Vary-Search'));
    }
}
