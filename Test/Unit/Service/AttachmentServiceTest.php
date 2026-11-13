<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Service;

use MageOS\RMA\Api\AttachmentRepositoryInterface;
use MageOS\RMA\Api\Data\AttachmentInterface;
use MageOS\RMA\Api\Data\AttachmentInterfaceFactory;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\ResourceModel\Attachment\CollectionFactory as AttachmentCollectionFactory;
use MageOS\RMA\Service\AttachmentService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Mime;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\MediaStorage\Model\File\UploaderFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AttachmentServiceTest extends TestCase
{
    private WriteInterface&MockObject $varDirectory;
    private AttachmentService $service;

    protected function setUp(): void
    {
        $this->varDirectory = $this->createMock(WriteInterface::class);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($this->varDirectory);

        $this->service = new AttachmentService(
            $filesystem,
            $this->createMock(UploaderFactory::class),
            $this->createMock(ModuleConfig::class),
            $this->createMock(AttachmentInterfaceFactory::class),
            $this->createMock(AttachmentRepositoryInterface::class),
            $this->createMock(AttachmentCollectionFactory::class),
            $this->createMock(Mime::class),
            $this->createMock(JsonSerializer::class)
        );
    }

    // -------------------------------------------------------------------------
    // getAbsolutePath — path traversal guard
    // -------------------------------------------------------------------------

    public function testGetAbsolutePathReturnsResolvedPathForValidFile(): void
    {
        $attachment = $this->createMock(AttachmentInterface::class);
        $attachment->method('getFilePath')->willReturn('rma/attachments/42/receipt.pdf');

        $this->varDirectory->method('getAbsolutePath')
            ->willReturnMap([
                ['rma/attachments/42/receipt.pdf', '/var/www/html/var/rma/attachments/42/receipt.pdf'],
                [AttachmentService::BASE_PATH,      '/var/www/html/var/rma/attachments'],
            ]);

        $result = $this->service->getAbsolutePath($attachment);

        $this->assertSame('/var/www/html/var/rma/attachments/42/receipt.pdf', $result);
    }

    public function testGetAbsolutePathThrowsOnPathTraversalAttempt(): void
    {
        $this->expectException(LocalizedException::class);

        $attachment = $this->createMock(AttachmentInterface::class);
        $attachment->method('getFilePath')->willReturn('rma/attachments/../../etc/passwd');

        $this->varDirectory->method('getAbsolutePath')
            ->willReturnMap([
                ['rma/attachments/../../etc/passwd', '/var/www/html/var/etc/passwd'],
                [AttachmentService::BASE_PATH,        '/var/www/html/var/rma/attachments'],
            ]);

        $this->service->getAbsolutePath($attachment);
    }

    public function testGetAbsolutePathThrowsWhenPathIsOutsideBaseDir(): void
    {
        $this->expectException(LocalizedException::class);

        $attachment = $this->createMock(AttachmentInterface::class);
        $attachment->method('getFilePath')->willReturn('rma/tmp/malicious.php');

        $this->varDirectory->method('getAbsolutePath')
            ->willReturnMap([
                ['rma/tmp/malicious.php',      '/var/www/html/var/rma/tmp/malicious.php'],
                [AttachmentService::BASE_PATH,  '/var/www/html/var/rma/attachments'],
            ]);

        $this->service->getAbsolutePath($attachment);
    }

    public function testGetAbsolutePathThrowsWhenPathMatchesPrefixButIsNotChild(): void
    {
        $this->expectException(LocalizedException::class);

        // Without the trailing-slash guard, str_starts_with('/var/.../rma/attachments-evil/...', '/var/.../rma/attachments')
        // would return TRUE and allow the path through. The fix normalises basePath to end with '/'.
        $attachment = $this->createMock(AttachmentInterface::class);
        $attachment->method('getFilePath')->willReturn('rma/attachments-evil/file.php');

        $this->varDirectory->method('getAbsolutePath')
            ->willReturnMap([
                ['rma/attachments-evil/file.php', '/var/www/html/var/rma/attachments-evil/file.php'],
                [AttachmentService::BASE_PATH,     '/var/www/html/var/rma/attachments'],
            ]);

        $this->service->getAbsolutePath($attachment);
    }

    // -------------------------------------------------------------------------
    // formatFileSize
    // -------------------------------------------------------------------------

    #[DataProvider('fileSizeProvider')]
    public function testFormatFileSize(int $bytes, string $expected): void
    {
        $this->assertSame($expected, $this->service->formatFileSize($bytes));
    }

    public static function fileSizeProvider(): array
    {
        return [
            'bytes'      => [512,           '512 B'],
            'exactly 1KB'=> [1024,          '1 KB'],
            'kilobytes'  => [2048,          '2 KB'],
            'megabytes'  => [1048576,       '1 MB'],
            'fractional' => [1572864,       '1.5 MB'],
            'zero bytes' => [0,             '0 B'],
        ];
    }
}
