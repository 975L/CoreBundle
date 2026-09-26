<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Model\QrCodeImage;
use c975L\UiBundle\Model\QrCodeOptions;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\Font;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Draws QR codes, each built once then read back from the cache: a code is derived data, rebuilt at will from what it encodes and how it is drawn - both in its key - so it never needs a file of its own. Whoever owns what a code points at drops it by one of the tags it was given when that changes (a renamed slug, a deleted shortcut)
class QrCodeGenerator
{
    // Carried by every code, so all of them can be dropped at once
    public const string CACHE_TAG = 'ui_qrcode';

    // Space under a label, as the PNG writer leaves it
    private const int LABEL_MARGIN_BOTTOM = 10;

    // A day: the url a code encodes may be retargeted, and a browser would otherwise keep showing the old one
    private const int HTTP_MAX_AGE = 86400;

    public function __construct(private readonly TagAwareCacheInterface $cache)
    {
    }

    // The code of the given data, drawn on the first call only - or every time when not cached, for options a visitor picks freely, each of which would otherwise leave an entry behind
    /** @param string[] $tags the owner's own tags, to drop this code with what it points at */
    public function generate(string $data, QrCodeOptions $options = new QrCodeOptions(), array $tags = [], bool $cached = true): QrCodeImage
    {
        if (!$cached) {
            return $this->draw($data, $options);
        }

        $key = 'ui_qrcode_' . hash('xxh128', serialize([$data, $options]));

        return $this->cache->get($key, function (ItemInterface $item) use ($data, $options, $tags): QrCodeImage {
            $item->tag([self::CACHE_TAG, ...$tags]);

            return $this->draw($data, $options);
        });
    }

    // The code sent back to a browser, which keeps it and later only asks whether it changed - "public" lets a proxy keep it too, never wanted for a code drawn in the back office
    public function response(QrCodeImage $image, Request $request, bool $public = false): Response
    {
        $response = new Response($image->content, Response::HTTP_OK, ['Content-Type' => $image->mimeType]);
        $response->setEtag(hash('xxh128', $image->content));
        $response->setMaxAge(self::HTTP_MAX_AGE);
        $response->setPrivate();

        // The same code for every visitor: kept as sent even when the session was read, which would otherwise turn it private and uncached
        if ($public) {
            $response->setPublic();
            $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        }

        $response->isNotModified($request);

        return $response;
    }

    // Only ever reached on a cache miss
    private function draw(string $data, QrCodeOptions $options): QrCodeImage
    {
        $isSvg = QrCodeOptions::FORMAT_SVG === $options->format;
        $font = null === $options->labelFontPath ? new OpenSans($options->labelFontSize) : new Font($options->labelFontPath, $options->labelFontSize);

        $result = new Builder()->build(
            writer: $isSvg ? new SvgWriter() : new PngWriter(),
            data: $data,
            errorCorrectionLevel: null === $options->logoPath ? ErrorCorrectionLevel::Low : ErrorCorrectionLevel::High,
            size: $options->size,
            margin: $options->margin,
            foregroundColor: $this->color($options->color),
            backgroundColor: $this->color($options->backgroundColor),
            labelText: $isSvg ? '' : $options->label ?? '',
            labelFont: $font,
            labelTextColor: $this->color($options->color),
            logoPath: $options->logoPath ?? '',
            logoResizeToWidth: $options->logoWidth,
        );

        $content = $result->getString();
        if ($isSvg && null !== $options->label && '' !== $options->label) {
            $content = $this->addSvgLabel($content, $options->label, $font->getPath(), $options);
        }

        return new QrCodeImage($content, $result->getMimeType());
    }

    // The SVG writer ignores the label: it is added below the code as the PNG writer lays it out, measured with the same font so its width matches - the viewer draws it with that family when installed, stretched to that width otherwise
    private function addSvgLabel(string $content, string $label, string $fontPath, QrCodeOptions $options): string
    {
        $box = imagettfbbox($options->labelFontSize, 0, $fontPath, $label);
        if (false === $box) {
            return $content;
        }

        $svg = new \SimpleXMLElement($content);
        $viewBox = explode(' ', (string) $svg['viewBox']);
        $width = (float) $viewBox[2];
        $codeHeight = (float) $viewBox[3];
        $labelWidth = $box[2] - $box[0];
        $height = $codeHeight + ($box[0] - $box[7]) + self::LABEL_MARGIN_BOTTOM;

        $svg['viewBox'] = '0 0 ' . $width . ' ' . $height;
        if (isset($svg['height'])) {
            $svg['height'] = $height . 'px';
        }

        $background = $svg->addChild('rect');
        $background->addAttribute('x', '0');
        $background->addAttribute('y', (string) $codeHeight);
        $background->addAttribute('width', (string) $width);
        $background->addAttribute('height', (string) ($height - $codeHeight));
        $background->addAttribute('fill', '#' . $this->hex($options->backgroundColor));

        // GD reads a font size in points at 96 dpi
        $font = new FontFilenameParser()->parse($fontPath);
        $text = $svg->addChild('text', htmlspecialchars($label, \ENT_XML1));
        $text->addAttribute('x', (string) (($width - $labelWidth) / 2));
        $text->addAttribute('y', (string) ($height - self::LABEL_MARGIN_BOTTOM));
        $text->addAttribute('fill', '#' . $this->hex($options->color));
        $text->addAttribute('font-family', "'" . $font['name'] . "', sans-serif");
        $text->addAttribute('font-weight', (string) $font['weight']);
        $text->addAttribute('font-size', (string) round($options->labelFontSize * 96 / 72, 2));
        $text->addAttribute('textLength', (string) $labelWidth);
        $text->addAttribute('lengthAdjust', 'spacingAndGlyphs');

        return (string) $svg->asXML();
    }

    // A 3 or 6 characters hexadecimal code, with or without "#"
    private function color(string $hex): Color
    {
        $hex = $this->hex($hex);

        return new Color((int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2)));
    }

    // A 3 or 6 characters hexadecimal code as 6 characters, without "#"
    private function hex(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return 3 === strlen($hex) ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] : $hex;
    }
}
