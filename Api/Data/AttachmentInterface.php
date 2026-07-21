<?php

declare(strict_types=1);

namespace MageOS\RMA\Api\Data;

/**
 * @api
 */
interface AttachmentInterface
{
    const ENTITY_ID = 'entity_id';
    const RMA_ID = 'rma_id';
    const COMMENT_ID = 'comment_id';
    const FILE_NAME = 'file_name';
    const FILE_PATH = 'file_path';
    const FILE_SIZE = 'file_size';
    const MIME_TYPE = 'mime_type';
    const CREATED_AT = 'created_at';

    /**
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @param int $entityId
     * @return $this
     */
    public function setEntityId(int $entityId): self;

    /**
     * @return int
     */
    public function getRmaId(): int;

    /**
     * @param int $rmaId
     * @return $this
     */
    public function setRmaId(int $rmaId): self;

    /**
     * @return int|null
     */
    public function getCommentId(): ?int;

    /**
     * @param int|null $commentId
     * @return $this
     */
    public function setCommentId(?int $commentId): self;

    /**
     * @return string
     */
    public function getFileName(): string;

    /**
     * @param string $fileName
     * @return $this
     */
    public function setFileName(string $fileName): self;

    /**
     * @return string
     */
    public function getFilePath(): string;

    /**
     * @param string $filePath
     * @return $this
     */
    public function setFilePath(string $filePath): self;

    /**
     * @return int
     */
    public function getFileSize(): int;

    /**
     * @param int $fileSize
     * @return $this
     */
    public function setFileSize(int $fileSize): self;

    /**
     * @return string
     */
    public function getMimeType(): string;

    /**
     * @param string $mimeType
     * @return $this
     */
    public function setMimeType(string $mimeType): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;
}
