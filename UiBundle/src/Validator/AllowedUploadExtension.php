<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator;

use Symfony\Component\Validator\Constraint;

// The file extensions an upload may carry, on a form's file field - the one list UiMediaNamer stores names from too, so a file past this door and a name on the disk cannot disagree. Not Symfony's File "extensions" option, which also compares the type read off the bytes and so refuses an SVG detected as text. No script, markup or server configuration in here, ever: php, html, xml and what is served as xml (gpx, kml), js, htaccess, ini, sh. A file under public/ with such a name is what a web server runs, or a browser opens on the site's own origin. SVG is the one exception, kept for the logos and icons sites do store, and it does run its scripts when opened directly
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class AllowedUploadExtension extends Constraint
{
    public const array EXTENSIONS = [
        // Images
        'jpg', 'jpeg', 'jpe', 'jfif', 'png', 'gif', 'webp', 'ico', 'svg', 'bmp', 'tif', 'tiff', 'heic', 'heif',
        // Documents
        'pdf', 'txt', 'csv', 'rtf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'epub', 'mobi',
        // Archives
        'zip', 'rar', '7z', 'gz', 'tar',
        // Audio
        'mp3', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'wav', 'flac', 'm4b', 'aif', 'aiff',
        // Video
        'mp4', 'm4v', 'mov', 'webm', 'ogv', 'mkv', 'avi', 'mpg', 'mpeg',
        // Fonts
        'ttf', 'otf', 'woff', 'woff2', 'eot',
        // Data
        'vtt', 'srt', 'ics',
    ];

    // What is refused whatever the file is named, read off its bytes: avif, which a site never takes in anymore, an avif already stored being served as before
    public const array REFUSED_MIME_TYPES = ['image/avif'];

    public string $message = 'label.upload_extension_not_allowed';
}
