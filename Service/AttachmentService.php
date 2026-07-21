<?php

declare(strict_types=1);

namespace MageOS\RMA\Service;

use Magento\Framework\Exception\FileSystemException;
use MageOS\RMA\Api\AttachmentRepositoryInterface;
use MageOS\RMA\Api\Data\AttachmentInterface;
use MageOS\RMA\Api\Data\AttachmentInterfaceFactory;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\ResourceModel\Attachment\CollectionFactory as AttachmentCollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\File\Mime;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\MediaStorage\Model\File\UploaderFactory;
use MageOS\RMA\Model\Config\Source\AllowedExtensions;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class AttachmentService
{
    const BASE_TMP_PATH = 'rma/tmp';
    const BASE_PATH = 'rma/attachments';
    const BYTES_PER_KB = 1024;
    const BYTES_PER_MB = 1024 * 1024;

    protected WriteInterface $varDirectory;

    /**
     * @param Filesystem $filesystem
     * @param UploaderFactory $uploaderFactory
     * @param ModuleConfig $moduleConfig
     * @param AttachmentInterfaceFactory $attachmentFactory
     * @param AttachmentRepositoryInterface $attachmentRepository
     * @param AttachmentCollectionFactory $attachmentCollectionFactory
     * @param Mime $mime
     * @param JsonSerializer $jsonSerializer
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $filesystem,
        protected readonly UploaderFactory $uploaderFactory,
        protected readonly ModuleConfig $moduleConfig,
        protected readonly AttachmentInterfaceFactory $attachmentFactory,
        protected readonly AttachmentRepositoryInterface $attachmentRepository,
        protected readonly AttachmentCollectionFactory $attachmentCollectionFactory,
        protected readonly Mime $mime,
        protected readonly JsonSerializer $jsonSerializer
    ) {
        $this->varDirectory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    /**
     * @param string $fileId
     * @return array
     * @throws LocalizedException
     */
    public function uploadToTmp(string $fileId): array
    {
        $maxBytes = $this->moduleConfig->getMaxAttachmentFileSizeBytes();

        if (isset($_FILES[$fileId]['size']) && $_FILES[$fileId]['size'] > $maxBytes) {
            throw new LocalizedException(
                __('File exceeds the maximum allowed size of %1 MB.', $this->moduleConfig->getMaxAttachmentFileSize())
            );
        }

        $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        $uploader->setAllowedExtensions($this->moduleConfig->getAllowedAttachmentExtensions());
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);

        if (!$uploader->checkMimeType($this->getAllowedMimeTypes())) {
            throw new LocalizedException(__('File type not allowed.'));
        }

        $tmpPath = $this->varDirectory->getAbsolutePath(self::BASE_TMP_PATH);
        $result = $uploader->save($tmpPath);

        if (!$result) {
            throw new LocalizedException(__('File cannot be saved to the temporary folder.'));
        }

        $fullPath = $tmpPath . '/' . $result['file'];
        $fileSize = (int)filesize($fullPath);

        if ($fileSize > $maxBytes) {
            $this->varDirectory->delete(self::BASE_TMP_PATH . '/' . $result['file']);
            throw new LocalizedException(
                __('File exceeds the maximum allowed size of %1 MB.', $this->moduleConfig->getMaxAttachmentFileSize())
            );
        }

        return [
            'file' => $result['file'],
            'name' => $result['name'] ?? $result['file'],
            'size' => $fileSize,
            'type' => $result['type'] ?? '',
            'tmp_path' => self::BASE_TMP_PATH . '/' . $result['file'],
        ];
    }

    /**
     * @param int $rmaId
     * @param array $tmpFiles
     * @param int|null $commentId
     * @return AttachmentInterface[]
     * @throws LocalizedException
     */
    public function moveFromTmpAndSave(int $rmaId, array $tmpFiles, ?int $commentId = null): array
    {
        $maxFiles = $this->moduleConfig->getMaxAttachmentFiles();
        $saved = [];

        foreach (array_slice($tmpFiles, 0, $maxFiles) as $tmpFile) {
            $fileName = basename($tmpFile['file'] ?? '');
            if ($fileName === '') {
                continue;
            }

            $sourcePath = self::BASE_TMP_PATH . '/' . $fileName;
            $destDir = self::BASE_PATH . '/' . $rmaId;
            $destPath = $destDir . '/' . $fileName;

            if (!$this->varDirectory->isExist($sourcePath)) {
                continue;
            }

            $this->varDirectory->create($destDir);
            $this->varDirectory->renameFile($sourcePath, $destPath);

            $absolutePath = $this->varDirectory->getAbsolutePath($destPath);
            $mimeType = $this->mime->getMimeType($absolutePath);

            $attachment = $this->attachmentFactory->create();
            $attachment->setRmaId($rmaId);
            $attachment->setCommentId($commentId);
            $attachment->setFileName($tmpFile['name'] ?? $fileName);
            $attachment->setFilePath($destPath);
            $attachment->setFileSize((int)($tmpFile['size'] ?? 0));
            $attachment->setMimeType($mimeType);

            $this->attachmentRepository->save($attachment);
            $saved[] = $attachment;
        }

        return $saved;
    }

    /**
     * @param int $rmaId
     * @return AttachmentInterface[]
     */
    public function getByRmaId(int $rmaId): array
    {
        $collection = $this->attachmentCollectionFactory->create();
        $collection->addFieldToFilter(AttachmentInterface::RMA_ID, $rmaId);
        $collection->setOrder(AttachmentInterface::CREATED_AT, 'ASC');

        return $collection->getItems();
    }

    /**
     * @param int $commentId
     * @return AttachmentInterface[]
     */
    public function getByCommentId(int $commentId): array
    {
        $collection = $this->attachmentCollectionFactory->create();
        $collection->addFieldToFilter(AttachmentInterface::COMMENT_ID, $commentId);
        $collection->setOrder(AttachmentInterface::CREATED_AT, 'ASC');

        return $collection->getItems();
    }

    /**
     * @param AttachmentInterface $attachment
     * @return string
     * @throws LocalizedException
     */
    public function getAbsolutePath(AttachmentInterface $attachment): string
    {
        $resolved = $this->varDirectory->getAbsolutePath($attachment->getFilePath());
        $basePath = rtrim($this->varDirectory->getAbsolutePath(self::BASE_PATH), '/') . '/';

        if (!str_starts_with($resolved, $basePath)) {
            throw new LocalizedException(__('Invalid attachment path.'));
        }

        return $resolved;
    }

    /**
     * @param AttachmentInterface $attachment
     * @return void
     * @throws LocalizedException
     */
    public function deleteAttachment(AttachmentInterface $attachment): void
    {
        $filePath = $attachment->getFilePath();

        if ($this->varDirectory->isExist($filePath)) {
            $this->varDirectory->delete($filePath);
        }

        $this->attachmentRepository->delete($attachment);
    }

    /**
     * @param string $json
     * @param int $rmaId
     * @param int|null $commentId
     * @return void
     * @throws LocalizedException
     */
    public function saveFromJson(string $json, int $rmaId, ?int $commentId = null): void
    {
        if ($json === '' || $json === '[]') {
            return;
        }

        $tmpFiles = $this->jsonSerializer->unserialize($json);
        if (!is_array($tmpFiles) || empty($tmpFiles)) {
            return;
        }

        $this->moveFromTmpAndSave($rmaId, $tmpFiles, $commentId);
    }

    /**
     * @param AttachmentInterface $attachment
     * @param FileFactory $fileFactory
     * @return ResponseInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createDownloadResponse(
        AttachmentInterface $attachment,
        FileFactory $fileFactory
    ): ResponseInterface {
        $filePath = $this->getAbsolutePath($attachment);

        if (!file_exists($filePath)) {
            throw new NoSuchEntityException(__('File not found.'));
        }

        return $fileFactory->create(
            $attachment->getFileName(),
            ['type' => 'filename', 'value' => $filePath],
            DirectoryList::VAR_DIR,
            $attachment->getMimeType()
        );
    }

    /**
     * @param int $bytes
     * @return string
     */
    public function formatFileSize(int $bytes): string
    {
        if ($bytes < self::BYTES_PER_KB) {
            return $bytes . ' B';
        }

        if ($bytes < self::BYTES_PER_MB) {
            return round($bytes / self::BYTES_PER_KB, 1) . ' KB';
        }

        return round($bytes / self::BYTES_PER_MB, 1) . ' MB';
    }

    /**
     * Returns the MIME types permitted for the currently configured extensions.
     *
     * @return string[]
     */
    private function getAllowedMimeTypes(): array
    {
        $allowed = [];
        foreach ($this->moduleConfig->getAllowedAttachmentExtensions() as $ext) {
            if (isset(AllowedExtensions::EXTENSION_MIME_MAP[$ext])) {
                array_push($allowed, ...AllowedExtensions::EXTENSION_MIME_MAP[$ext]);
            }
        }

        return array_unique($allowed);
    }

    /**
     * @param AttachmentInterface $attachment
     * @return array
     */
    public function toArray(AttachmentInterface $attachment): array
    {
        return [
            'entity_id' => $attachment->getEntityId(),
            'rma_id' => $attachment->getRmaId(),
            'comment_id' => $attachment->getCommentId(),
            'file_name' => $attachment->getFileName(),
            'file_size' => $attachment->getFileSize(),
            'file_size_label' => $this->formatFileSize((int)$attachment->getFileSize()),
            'mime_type' => $attachment->getMimeType(),
            'created_at' => $attachment->getCreatedAt(),
        ];
    }
}
