<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Mcp;

use c975L\ConfigBundle\Mcp\McpTool;
use c975L\ConfigBundle\Mcp\McpToolProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Registry\MediaUsageRegistry;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Twig\MediaUrlExtension;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// The media library on the site's MCP endpoint, for an account that edits it on the back office (see MediaCrudController): listed, read with where each media is used, its alt text and caption written, uploading, replacing and deleting a file left to a human
class UiMcpProvider implements McpToolProviderInterface
{
    // Medias a list gives by default
    public const int LIMIT = 50;

    // Medias a list gives at most
    public const int MAX_LIMIT = 100;

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly MediaUsageRegistry $mediaUsageRegistry,
        private readonly MediaUrlExtension $mediaUrl,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
    ) {
    }

    public function getMcpKey(): string
    {
        return 'ui';
    }

    public function getMcpInstructions(): string
    {
        return 'The media library holds every file of the site: images, documents, videos. An agent reads it and writes a media\'s alt text and caption; uploading, replacing and deleting a file stay a human\'s, from the back office, and a site graphic (logo, favicon...) is edited on its own screen. An alt text or a caption written shows on the site at once.';
    }

    public function getMcpTools(): iterable
    {
        yield new McpTool('ui_media_list', 'Media library', 'The medias whose alt text, caption, name or file name holds the search words, every media without them, only those without an alt text when asked, the latest first, each with its url, type, size and where it is used.', $this->list(...));
        yield new McpTool('ui_media_get', 'Media', 'A media: its file, url, type, size, dimensions, alt text, caption, credits, site graphic role and where it is used.', $this->get(...));
        yield new McpTool('ui_media_edit', 'Write a media', 'Changes only the alt text or caption given of a media, an empty one clearing it, its file kept; a site graphic is left to its own screen.', $this->edit(...), true);
    }

    // The medias whose texts hold the words, every one without them, the latest first, each with where it is used
    /**
     * @param ?string $search     words looked for in the alt text, caption, name and file name
     * @param ?int    $limit      medias given, 50 by default, 100 at most
     * @param bool    $withoutAlt only the medias without an alt text
     *
     * @return list<array<string, mixed>>
     */
    public function list(?string $search = null, ?int $limit = null, bool $withoutAlt = false): array
    {
        $this->guard();
        $medias = $this->mediaRepository->findForAgents($search, $withoutAlt, max(1, min($limit ?? self::LIMIT, self::MAX_LIMIT)));
        $usages = $this->mediaUsageRegistry->getUsages($medias);

        return array_map(fn (Media $media): array => $this->summary($media, $usages), $medias);
    }

    // A media with its credits and the role it holds as a site graphic
    /**
     * @param int $media id of the media
     *
     * @return array<string, mixed>
     */
    public function get(int $media): array
    {
        $found = $this->found($media);

        return [
            ...$this->summary($found, $this->mediaUsageRegistry->getUsages([$found])),
            'name' => $found->getName(),
            'credits' => $found->getCredits(),
            'rightsReserved' => $found->isRightsReserved(),
            'membersOnly' => $found->isMembersOnly(),
        ];
    }

    // A media's alt text or caption changed, only those given, an empty one clearing it, each refused by the entity's own constraints before any is written - a site graphic refused, as the library leaves it to its own screen
    /**
     * @param int     $media   id of the media
     * @param ?string $alt     new alt text, empty to clear it
     * @param ?string $caption new caption, empty to clear it
     *
     * @return array{id: ?int, alt: ?string, caption: ?string}
     */
    public function edit(int $media, ?string $alt = null, ?string $caption = null): array
    {
        $found = $this->found($media);
        if (null !== $found->getRole()) {
            throw new ToolCallException(sprintf('The media %d is a site graphic: it is edited on the Site graphics screen.', $media));
        }

        $newAlt = $this->valid($found, 'alt', $alt);
        $newCaption = $this->valid($found, 'label', $caption);
        if (null !== $alt) {
            $found->setAlt($newAlt);
        }
        if (null !== $caption) {
            $found->setLabel($newCaption);
        }
        $this->entityManager->flush();

        return ['id' => $found->getId(), 'alt' => $found->getUntranslated('alt'), 'caption' => $found->getUntranslated('label')];
    }

    // The library is the editor's business, as on the back office
    private function guard(): void
    {
        if (!$this->security->isGranted((string) $this->configService->get('site-role-editor'))) {
            throw new ToolCallException('This account may not work on the media library.');
        }
    }

    // A media by its id, the role checked first so a refused account learns nothing of the ids
    private function found(int $id): Media
    {
        $this->guard();
        $media = $this->mediaRepository->find($id);
        if (!$media instanceof Media) {
            throw new ToolCallException(sprintf('No media %d.', $id));
        }

        return $media;
    }

    // A text trimmed, null when empty, refused when the property's constraints refuse it, as the media's form runs them
    private function valid(Media $media, string $property, ?string $value): ?string
    {
        $text = '' === trim((string) $value) ? null : trim((string) $value);
        $violations = $this->validator->validatePropertyValue($media, $property, $text);
        if (0 === \count($violations)) {
            return $text;
        }

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $property . ': ' . $violation->getMessage();
        }

        throw new ToolCallException(implode(' ', $messages));
    }

    // A media as the tools give it, the uploader's account never told
    /**
     * @param array<int, list<array{label: string, url: ?string, binned?: bool}>> $usages
     *
     * @return array<string, mixed>
     */
    private function summary(Media $media, array $usages): array
    {
        $filename = $media->getUntranslated('filename');

        return [
            'id' => $media->getId(),
            'file' => $filename,
            'url' => null === $filename || '' === $filename ? null : $this->mediaUrl->getUrl($media),
            'type' => $media->getMimeType(),
            'size' => $media->getSize(),
            'width' => $media->getWidth(),
            'height' => $media->getHeight(),
            'alt' => $media->getUntranslated('alt'),
            'caption' => $media->getUntranslated('label'),
            'role' => $media->getRole(),
            'usedIn' => array_column($usages[(int) $media->getId()] ?? [], 'label'),
        ];
    }
}
