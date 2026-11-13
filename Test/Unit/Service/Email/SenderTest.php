<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Service\Email;

use ArrayIterator;
use MageOS\RMA\Api\Data\ReasonInterface;
use MageOS\RMA\Api\Data\ResolutionTypeInterface;
use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Data\StatusInterface;
use MageOS\RMA\Api\Email\SenderInterface;
use MageOS\RMA\Api\ItemConditionRepositoryInterface;
use MageOS\RMA\Api\ReasonRepositoryInterface;
use MageOS\RMA\Api\ResolutionTypeRepositoryInterface;
use MageOS\RMA\Api\StatusRepositoryInterface;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\ResourceModel\Item\Collection as ItemCollection;
use MageOS\RMA\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;
use MageOS\RMA\Service\Email\Sender;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SenderTest extends TestCase
{
    private TransportBuilder&MockObject $transportBuilder;
    private TransportInterface&MockObject $transport;
    private ModuleConfig&MockObject $moduleConfig;
    private LoggerInterface&MockObject $logger;
    private Emulation&MockObject $appEmulation;
    private EventManagerInterface&MockObject $eventManager;
    private RMAInterface&MockObject $rma;
    private Sender $sender;

    /**
     * @var array<int, array{0: string, 1: array}>
     */
    private array $dispatched = [];

    /**
     * @var callable|null
     */
    private $observer = null;

    private ?string $usedTemplateId = null;
    private ?array $usedTemplateVars = null;

    protected function setUp(): void
    {
        $this->transportBuilder = $this->createMock(TransportBuilder::class);
        $this->transport = $this->createMock(TransportInterface::class);
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->appEmulation = $this->createMock(Emulation::class);
        $this->eventManager = $this->createMock(EventManagerInterface::class);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $reasonRepository = $this->createMock(ReasonRepositoryInterface::class);
        $resolutionTypeRepository = $this->createMock(ResolutionTypeRepositoryInterface::class);
        $statusRepository = $this->createMock(StatusRepositoryInterface::class);
        $itemCollectionFactory = $this->createMock(ItemCollectionFactory::class);

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnCallback(
            function (string $templateId): TransportBuilder {
                $this->usedTemplateId = $templateId;
                return $this->transportBuilder;
            }
        );
        $this->transportBuilder->method('setTemplateVars')->willReturnCallback(
            function (array $templateVars): TransportBuilder {
                $this->usedTemplateVars = $templateVars;
                return $this->transportBuilder;
            }
        );
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willReturn($this->transport);

        $this->eventManager->method('dispatch')->willReturnCallback(
            function (string $eventName, array $data = []): void {
                $this->dispatched[] = [$eventName, $data];
                if ($this->observer !== null) {
                    ($this->observer)($data);
                }
            }
        );

        $this->moduleConfig->method('getCustomerNewTemplate')->willReturn('rma_customer_new');
        $this->moduleConfig->method('getCustomerStatusChangeTemplate')->willReturn('rma_customer_status_change');
        $this->moduleConfig->method('getAdminNewTemplate')->willReturn('rma_admin_new');
        $this->moduleConfig->method('getAdminNotifyEmail')->willReturn('admin@example.com');
        $this->moduleConfig->method('getEmailSenderIdentity')->willReturn('general');

        $status = $this->createMock(StatusInterface::class);
        $status->method('getStoreLabel')->willReturn('Approved');
        $statusRepository->method('get')->willReturn($status);

        $reason = $this->createMock(ReasonInterface::class);
        $reason->method('getStoreLabel')->willReturn('Wrong size');
        $reasonRepository->method('get')->willReturn($reason);

        $resolutionType = $this->createMock(ResolutionTypeInterface::class);
        $resolutionType->method('getStoreLabel')->willReturn('Refund');
        $resolutionTypeRepository->method('get')->willReturn($resolutionType);

        $orderRepository->method('get')->willReturn($this->createMock(OrderInterface::class));
        $storeManager->method('getStore')->willReturn($this->createMock(StoreInterface::class));

        $itemCollection = $this->createMock(ItemCollection::class);
        $itemCollection->method('addFieldToFilter')->willReturnSelf();
        $itemCollection->method('getIterator')->willReturn(new ArrayIterator([]));
        $itemCollectionFactory->method('create')->willReturn($itemCollection);

        $this->rma = $this->createMock(RMAInterface::class);
        $this->rma->method('getStoreId')->willReturn(1);
        $this->rma->method('getOrderId')->willReturn(5);
        $this->rma->method('getReasonId')->willReturn(3);
        $this->rma->method('getResolutionTypeId')->willReturn(4);
        $this->rma->method('getCustomerEmail')->willReturn('customer@example.com');
        $this->rma->method('getCustomerName')->willReturn('John Doe');

        $this->sender = new Sender(
            $this->transportBuilder,
            $storeManager,
            $orderRepository,
            $reasonRepository,
            $resolutionTypeRepository,
            $this->moduleConfig,
            $this->logger,
            $itemCollectionFactory,
            $this->createMock(OrderItemRepositoryInterface::class),
            $this->createMock(ProductRepositoryInterface::class),
            $this->createMock(ImageHelper::class),
            $this->createMock(ItemConditionRepositoryInterface::class),
            $statusRepository,
            $this->appEmulation,
            $this->eventManager
        );
    }

    public function testStatusChangeEmailDispatchesEventBeforeSending(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->transport->expects($this->once())->method('sendMessage');

        $this->sender->sendCustomerStatusChangeEmail($this->rma, 2);

        $this->assertCount(1, $this->dispatched);
        [$eventName, $data] = $this->dispatched[0];
        $this->assertSame('rma_email_send_before', $eventName);
        $this->assertSame($this->rma, $data['rma']);
        $this->assertSame(SenderInterface::EMAIL_TYPE_CUSTOMER_STATUS_CHANGE, $data['email_type']);
        $this->assertInstanceOf(DataObject::class, $data['transport']);
        $this->assertSame('rma_customer_status_change', $data['transport']->getData('template_id'));

        $templateVars = $data['transport']->getData('template_vars');
        $this->assertSame('Approved', $templateVars['rma_status_label']);
        $this->assertSame('John Doe', $templateVars['customer_name']);
        $this->assertSame($this->rma, $templateVars['rma']);

        $this->assertSame('rma_customer_status_change', $this->usedTemplateId);
        $this->assertSame($templateVars, $this->usedTemplateVars);
    }

    public function testObserverCanChangeTemplateAndTemplateVariables(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->transport->expects($this->once())->method('sendMessage');

        $this->observer = static function (array $data): void {
            $transport = $data['transport'];
            $transport->setData('template_id', 'my_module_rma_refunded');
            $transport->setData(
                'template_vars',
                $transport->getData('template_vars') + ['refunded_amount' => '19.90']
            );
        };

        $this->sender->sendCustomerStatusChangeEmail($this->rma, 2);

        $this->assertSame('my_module_rma_refunded', $this->usedTemplateId);
        $this->assertSame('19.90', $this->usedTemplateVars['refunded_amount']);
        $this->assertSame('Approved', $this->usedTemplateVars['rma_status_label']);
    }

    public function testNewRmaEmailsDispatchTheirOwnEmailTypes(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->transport->expects($this->exactly(2))->method('sendMessage');

        $this->sender->sendCustomerNewRmaEmail($this->rma);
        $this->sender->sendAdminNewRmaEmail($this->rma);

        $this->assertCount(2, $this->dispatched);
        $this->assertSame(SenderInterface::EMAIL_TYPE_CUSTOMER_NEW, $this->dispatched[0][1]['email_type']);
        $this->assertSame('rma_customer_new', $this->dispatched[0][1]['transport']->getData('template_id'));
        $this->assertSame(SenderInterface::EMAIL_TYPE_ADMIN_NEW, $this->dispatched[1][1]['email_type']);
        $this->assertSame('rma_admin_new', $this->dispatched[1][1]['transport']->getData('template_id'));
    }

    public function testNoEventIsDispatchedWhenModuleIsDisabled(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(false);
        $this->transport->expects($this->never())->method('sendMessage');

        $this->sender->sendCustomerNewRmaEmail($this->rma);
        $this->sender->sendCustomerStatusChangeEmail($this->rma, 2);
        $this->sender->sendAdminNewRmaEmail($this->rma);

        $this->assertSame([], $this->dispatched);
    }

    public function testFailingObserverIsLoggedLikeAnyOtherSendFailure(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->transport->expects($this->never())->method('sendMessage');
        $this->logger->expects($this->once())->method('error');
        $this->appEmulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->observer = static function (): void {
            throw new RuntimeException('Observer failed');
        };

        $this->sender->sendCustomerStatusChangeEmail($this->rma, 2);
    }
}
