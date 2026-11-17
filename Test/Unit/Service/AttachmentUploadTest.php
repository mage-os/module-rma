<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Service;

use MageOS\RMA\Api\AttachmentRepositoryInterface;
use MageOS\RMA\Api\Data\AttachmentInterfaceFactory;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\Config\Source\AllowedExtensions;
use MageOS\RMA\Model\ResourceModel\Attachment\CollectionFactory as AttachmentCollectionFactory;
use MageOS\RMA\Service\AttachmentService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Mime;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\MediaStorage\Model\File\Uploader;
use Magento\MediaStorage\Model\File\UploaderFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AttachmentUploadTest extends TestCase
{
    private WriteInterface&MockObject $varDirectory;
    private UploaderFactory&MockObject $uploaderFactory;
    private ModuleConfig&MockObject $moduleConfig;
    private AttachmentService $service;

    protected function setUp(): void
    {
        $this->varDirectory    = $this->createMock(WriteInterface::class);
        $this->uploaderFactory = $this->createMock(UploaderFactory::class);
        $this->moduleConfig    = $this->createMock(ModuleConfig::class);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($this->varDirectory);

        $this->service = new AttachmentService(
            $filesystem,
            $this->uploaderFactory,
            $this->moduleConfig,
            $this->createMock(AttachmentInterfaceFactory::class),
            $this->createMock(AttachmentRepositoryInterface::class),
            $this->createMock(AttachmentCollectionFactory::class),
            $this->createMock(Mime::class),
            $this->createMock(JsonSerializer::class)
        );
    }

    private function makeUploader(): Uploader&MockObject
    {
        $uploader = $this->createMock(Uploader::class);
        $uploader->method('setAllowedExtensions')->willReturnSelf();
        $uploader->method('setAllowRenameFiles')->willReturnSelf();
        $uploader->method('setFilesDispersion')->willReturnSelf();

        return $uploader;
    }

    // -------------------------------------------------------------------------
    // Pre-flight size check (before upload)
    // -------------------------------------------------------------------------

    public function testUploadToTmpThrowsWhenPreflightFileSizeExceedsLimit(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/maximum allowed size/');

        $this->moduleConfig->method('getMaxAttachmentFileSizeBytes')->willReturn(1024);
        $this->moduleConfig->method('getMaxAttachmentFileSize')->willReturn(1);

        $_FILES['rma_file'] = ['size' => 2048];

        $this->uploaderFactory->expects($this->never())->method('create');

        try {
            $this->service->uploadToTmp('rma_file');
        } finally {
            unset($_FILES['rma_file']);
        }
    }

    public function testUploadToTmpDoesNotThrowWhenFileSizeIsWithinLimit(): void
    {
        $this->moduleConfig->method('getMaxAttachmentFileSizeBytes')->willReturn(1024);
        $this->moduleConfig->method('getMaxAttachmentFileSize')->willReturn(1);
        $this->moduleConfig->method('getAllowedAttachmentExtensions')->willReturn(['jpg']);

        $_FILES['rma_file'] = ['size' => 512];

        $uploader = $this->makeUploader();
        $uploader->method('checkMimeType')->willReturn(false);

        $this->uploaderFactory->method('create')->willReturn($uploader);
        $this->varDirectory->method('getAbsolutePath')->willReturn('/var/www/html/var/rma/tmp');

        try {
            $this->service->uploadToTmp('rma_file');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('File type not allowed', $e->getMessage());
        } finally {
            unset($_FILES['rma_file']);
        }
    }

    // -------------------------------------------------------------------------
    // MIME type validation
    // -------------------------------------------------------------------------

    public function testUploadToTmpThrowsWhenMimeTypeNotAllowed(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/File type not allowed/');

        $this->moduleConfig->method('getMaxAttachmentFileSizeBytes')->willReturn(10 * 1024 * 1024);
        $this->moduleConfig->method('getAllowedAttachmentExtensions')->willReturn(['jpg', 'png']);

        $uploader = $this->makeUploader();
        $uploader->method('checkMimeType')->willReturn(false);

        $this->uploaderFactory->method('create')->willReturn($uploader);
        $this->varDirectory->method('getAbsolutePath')->willReturn('/var/www/html/var/rma/tmp');

        $this->service->uploadToTmp('rma_file');
    }

    public function testUploadToTmpPassesCorrectMimeTypesForConfiguredExtensions(): void
    {
        $this->moduleConfig->method('getMaxAttachmentFileSizeBytes')->willReturn(10 * 1024 * 1024);
        $this->moduleConfig->method('getAllowedAttachmentExtensions')->willReturn(['jpg', 'pdf']);

        $expectedMimeTypes = array_unique(array_merge(
            AllowedExtensions::EXTENSION_MIME_MAP['jpg'],
            AllowedExtensions::EXTENSION_MIME_MAP['pdf']
        ));

        $uploader = $this->makeUploader();
        $uploader->expects($this->once())
            ->method('checkMimeType')
            ->with($expectedMimeTypes)
            ->willReturn(false);

        $this->uploaderFactory->method('create')->willReturn($uploader);
        $this->varDirectory->method('getAbsolutePath')->willReturn('/var/www/html/var/rma/tmp');

        try {
            $this->service->uploadToTmp('rma_file');
        } catch (LocalizedException) {
            // expected — we only care that checkMimeType was called with the right types
        }
    }

    public function testUploadToTmpExcludesMimeTypesForUnconfiguredExtensions(): void
    {
        $this->moduleConfig->method('getMaxAttachmentFileSizeBytes')->willReturn(10 * 1024 * 1024);
        // Only jpg configured — zip should not appear in the allowed MIME list
        $this->moduleConfig->method('getAllowedAttachmentExtensions')->willReturn(['jpg']);

        $uploader = $this->makeUploader();
        $uploader->expects($this->once())
            ->method('checkMimeType')
            ->with($this->callback(function (array $mimeTypes): bool {
                return !in_array('application/zip', $mimeTypes, true)
                    && !in_array('application/x-zip-compressed', $mimeTypes, true);
            }))
            ->willReturn(false);

        $this->uploaderFactory->method('create')->willReturn($uploader);
        $this->varDirectory->method('getAbsolutePath')->willReturn('/var/www/html/var/rma/tmp');

        try {
            $this->service->uploadToTmp('rma_file');
        } catch (LocalizedException) {
        }
    }

    // -------------------------------------------------------------------------
    // Extension allowlist is wired to uploader
    // -------------------------------------------------------------------------

    public function testUploadToTmpPassesAllowedExtensionsToUploader(): void
    {
        $allowedExtensions = ['jpg', 'png', 'pdf'];

        $this->moduleConfig->method('getMaxAttachmentFileSizeBytes')->willReturn(10 * 1024 * 1024);
        $this->moduleConfig->method('getAllowedAttachmentExtensions')->willReturn($allowedExtensions);

        $uploader = $this->makeUploader();
        $uploader->expects($this->once())
            ->method('setAllowedExtensions')
            ->with($allowedExtensions);
        $uploader->method('checkMimeType')->willReturn(false);

        $this->uploaderFactory->method('create')->willReturn($uploader);
        $this->varDirectory->method('getAbsolutePath')->willReturn('/var/www/html/var/rma/tmp');

        try {
            $this->service->uploadToTmp('rma_file');
        } catch (LocalizedException) {
        }
    }

    public function testUploadToTmpPassesEmptyExtensionListWhenNoneConfigured(): void
    {
        $this->moduleConfig->method('getMaxAttachmentFileSizeBytes')->willReturn(10 * 1024 * 1024);
        $this->moduleConfig->method('getAllowedAttachmentExtensions')->willReturn([]);

        $uploader = $this->makeUploader();
        $uploader->expects($this->once())
            ->method('setAllowedExtensions')
            ->with([]);
        $uploader->method('checkMimeType')->willReturn(false);

        $this->uploaderFactory->method('create')->willReturn($uploader);
        $this->varDirectory->method('getAbsolutePath')->willReturn('/var/www/html/var/rma/tmp');

        try {
            $this->service->uploadToTmp('rma_file');
        } catch (LocalizedException) {
        }
    }

    public function testUploadToTmpStoresFileUnderRandomNameAndKeepsOriginalName(): void
    {
        $this->moduleConfig->method('getMaxAttachmentFileSizeBytes')->willReturn(10 * 1024 * 1024);
        $this->moduleConfig->method('getAllowedAttachmentExtensions')->willReturn(['pdf']);

        $tmpDir = sys_get_temp_dir() . '/rma-upload-test-' . bin2hex(random_bytes(4));
        mkdir($tmpDir);

        $uploader = $this->makeUploader();
        $uploader->method('checkMimeType')->willReturn(true);
        $uploader->method('getFileExtension')->willReturn('PDF');
        $uploader->expects($this->once())
            ->method('save')
            ->with($tmpDir, $this->matchesRegularExpression('/^[a-f0-9]{32}\.pdf$/'))
            ->willReturnCallback(function (string $dir, string $name): array {
                file_put_contents($dir . '/' . $name, 'x');
                return ['file' => $name, 'name' => 'invoice.pdf', 'type' => 'application/pdf'];
            });

        $this->uploaderFactory->method('create')->willReturn($uploader);
        $this->varDirectory->method('getAbsolutePath')->willReturn($tmpDir);

        try {
            $result = $this->service->uploadToTmp('rma_file');
        } finally {
            array_map('unlink', glob($tmpDir . '/*'));
            rmdir($tmpDir);
        }

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.pdf$/', $result['file']);
        $this->assertSame('invoice.pdf', $result['name']);
    }

    public function testUploadToTmpRejectsFileWithoutExtension(): void
    {
        $this->expectException(LocalizedException::class);

        $this->moduleConfig->method('getMaxAttachmentFileSizeBytes')->willReturn(10 * 1024 * 1024);
        $this->moduleConfig->method('getAllowedAttachmentExtensions')->willReturn([]);

        $uploader = $this->makeUploader();
        $uploader->method('checkMimeType')->willReturn(true);
        $uploader->method('getFileExtension')->willReturn('');
        $uploader->expects($this->never())->method('save');

        $this->uploaderFactory->method('create')->willReturn($uploader);
        $this->varDirectory->method('getAbsolutePath')->willReturn('/var/www/html/var/rma/tmp');

        $this->service->uploadToTmp('rma_file');
    }
}
