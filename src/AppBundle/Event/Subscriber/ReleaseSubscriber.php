<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace AppBundle\Event\Subscriber;

use eZ\Publish\API\Repository\ContentService as ContentServiceInterface;
use eZ\Publish\Core\MVC\Symfony\Event\SignalEvent;
use eZ\Publish\Core\MVC\Symfony\MVCEvents;
use eZ\Publish\Core\SignalSlot\Signal\ContentService\HideContentSignal;
use eZ\Publish\Core\SignalSlot\Signal\ContentService\PublishVersionSignal;
use eZ\Publish\Core\SignalSlot\Signal\ContentService\RevealContentSignal;
use eZ\Publish\Core\SignalSlot\Signal\LocationService\HideLocationSignal;
use eZ\Publish\Core\SignalSlot\Signal\LocationService\UnhideLocationSignal;
use eZ\Publish\Core\SignalSlot\Signal\TrashService\TrashSignal;
use EzSystems\PlatformHttpCacheBundle\Handler\TagHandler as TagHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReleaseSubscriber implements EventSubscriberInterface
{
    /** @var \EzSystems\PlatformHttpCacheBundle\Handler\TagHandler */
    private $tagHandler;

    /** @var \eZ\Publish\API\Repository\ContentService */
    private $contentService;

    /** @var int */
    private $releaseContentTypeId;

    /** @var int */
    private $downloadSectionLocationId;

    /**
     * @param \eZ\Publish\API\Repository\ContentService $contentService
     * @param \EzSystems\PlatformHttpCacheBundle\Handler\TagHandler $tagHandler
     * @param int $releaseContentTypeId
     * @param int $downloadSectionLocationId
     */
    public function __construct(
        ContentServiceInterface $contentService,
        TagHandlerInterface $tagHandler,
        int $releaseContentTypeId,
        int $downloadSectionLocationId
    ) {
        $this->contentService = $contentService;
        $this->tagHandler = $tagHandler;
        $this->releaseContentTypeId = $releaseContentTypeId;
        $this->downloadSectionLocationId = $downloadSectionLocationId;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MVCEvents::API_SIGNAL => [
                ['onPublishVersionSignal'],
                ['onTrashSignal'],
                ['onHideContentSignal'],
                ['onRevealContentSignal'],
                ['onHideLocationSignal'],
                ['onUnhideLocationSignal'],
            ],
        ];
    }

    /**
     * @param \eZ\Publish\Core\MVC\Symfony\Event\SignalEvent $event
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function onPublishVersionSignal(SignalEvent $event): void
    {
        if (!$event->getSignal() instanceof PublishVersionSignal) {
            return;
        }

        $this->invalidateDownloadTags((int) $event->getSignal()->contentId);
    }

    /**
     * @param \eZ\Publish\Core\MVC\Symfony\Event\SignalEvent $event
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function onHideLocationSignal(SignalEvent $event): void
    {
        if (!$event->getSignal() instanceof HideLocationSignal) {
            return;
        }

        $this->invalidateDownloadTags((int) $event->getSignal()->contentId);
    }

    /**
     * @param \eZ\Publish\Core\MVC\Symfony\Event\SignalEvent $event
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function onUnhideLocationSignal(SignalEvent $event): void
    {
        if (!$event->getSignal() instanceof UnhideLocationSignal) {
            return;
        }

        $this->invalidateDownloadTags((int) $event->getSignal()->contentId);
    }

    /**
     * @param \eZ\Publish\Core\MVC\Symfony\Event\SignalEvent $event
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function onTrashSignal(SignalEvent $event): void
    {
        if (!$event->getSignal() instanceof TrashSignal) {
            return;
        }

        $this->invalidateDownloadTags((int) $event->getSignal()->contentId);
    }

    /**
     * @param \eZ\Publish\Core\MVC\Symfony\Event\SignalEvent $event
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function onHideContentSignal(SignalEvent $event): void
    {
        if (!$event->getSignal() instanceof HideContentSignal) {
            return;
        }

        $this->invalidateDownloadTags((int) $event->getSignal()->contentId);
    }

    /**
     * @param \eZ\Publish\Core\MVC\Symfony\Event\SignalEvent $event
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function onRevealContentSignal(SignalEvent $event): void
    {
        if (!$event->getSignal() instanceof RevealContentSignal) {
            return;
        }

        $this->invalidateDownloadTags((int) $event->getSignal()->contentId);
    }

    /**
     * @param int $contentId
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    private function invalidateDownloadTags(int $contentId): void
    {
        $contentInfo = $this->contentService->loadContentInfo($contentId);

        if ($contentInfo->contentTypeId === $this->releaseContentTypeId) {
            $this->tagHandler->invalidateTags([
                'location-' . $this->downloadSectionLocationId
            ]);
        }
    }
}
