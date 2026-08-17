---
name: c975l-media
description: "Use this skill when handling uploads or images in a Symfony application built on the c975L ecosystem — the shared Media entity, the site-wide graphics, a satellite bundle's own Vich media entity, the three-sizes derivatives, keeping the untouched original, watermarking, private files, PDF thumbnails and the media library. Covers what is generated for you and must never be re-implemented. Triggers on: Media entity, VichMediaTrait, VichMediaNamableInterface, VichMultiSizeImageInterface, VichImageResizeListener, VichOriginalKeepableInterface, VichWatermarkableInterface, VichPrivateFileInterface, MediaFileRemoveListener, PrivateFileResponseFactory, site_media, favicon, logo, og-image, ROLE_WATERMARK, MediaUsageProviderInterface, PlaceholderMediaProviderInterface, thumbnail, highres, UploadProgress, upload progress bar, formAttr."
---

# c975L UiBundle — media and uploads

> One media library for the site, one trait for a bundle that needs its own, and a resize pipeline that no bundle should ever write twice.

**Package:** `c975l/core-bundle` · **Bundle:** `c975L\UiBundle\` · **Twig namespace:** `@c975LUi`

**Key source paths** (relative to this bundle's directory inside the package):
`src/Entity/Media.php`, `src/Entity/Trait/VichMediaTrait.php`, `src/Contract/`, `src/Listener/VichImageResizeListener.php`, `src/Listener/MediaFileRemoveListener.php`, `src/Service/ImageWatermarker.php`, `src/Service/PrivateFileResponseFactory.php`, `src/Service/UiMediaNamer.php`, `src/Service/UploadProgress.php`, `src/Controller/Management/`, `src/Form/VichImageOptions.php`, `assets/js/upload-progress.js`

**Related skills:** `c975l-blocks`, `c975l-forms-emails`, `c975l-ui-assets` in this same bundle.

## Two ways to hold a file

- **The shared `Media` entity** — attached to a `Block`, or holding one of the site-wide graphics by
  `role` (favicon, Apple touch icon, logo, default og-image, the error-image pool, the two watermarks).
  Managed from the Media library and the Site graphics screens, exported and imported with the rest.
- **A media entity of your own**, when a satellite bundle needs its own table (a gallery photo, a
  product picture). Use `Entity\Trait\VichMediaTrait` for the id/position/name/size/file/updatedAt/user
  fields: **no Doctrine relation to this bundle's `Media`, and therefore no dependency between two
  satellite bundles that both need one.**

On your own entity, implement `Contract\VichMediaNamableInterface::getVichMediaPath()` — the trait
deliberately does not, the path depending on your storage layout. That one method buys the naming
strategy and `MediaFileRemoveListener`, which deletes the file from `public/` when the row is removed,
with **no listener of your own to write**.

## Three sizes of one image

Implement `Contract\VichMultiSizeImageInterface`, declaring three widths and nothing else:

| Method | File | Used for |
| --- | --- | --- |
| `getImageWidth()` | the stored file, downscaled in place | what a page displays |
| `getThumbnailSize()` | `-thumb.webp` beside it | grids |
| `getHighresWidth()` | `-highres.webp` beside it | zoom |

`Listener\VichImageResizeListener` generates both siblings from the **untouched original**, never from
the already-downscaled stored file, and never upscales past it. Nothing is stored in the database for
them: the entity reads their names back from its own filename.

**The thumbnail keeps the image's proportions**, the longest side capped — it is square only for a
square original. A grid wanting square tiles uses `object-fit: cover`, which stays reversible where a
file cropped square could never give the pixels back.

The derivatives are plain files Vich knows nothing about, so **removing them is the consuming bundle's
own business** — a `postRemove` listener of yours.

A photo is straightened from its EXIF orientation tag once, before anything is measured, and
everything written is webp, a format saved without EXIF, so nothing downstream rotates it twice.

## Keeping the original, and signing it

- `Contract\VichOriginalKeepableInterface` **copies** the untouched upload into a directory of the
  entity's choosing (`private`, typically) — unlike a private file, which *moves* the stored file out
  of `public/`. The extension is decided on the mime type read off the file's own bytes, against an
  allow-list, never on the name the browser sent. Nothing serves or removes that copy: its lifecycle is
  yours, and a backed-up root grows by the full upload size.
- `Contract\VichWatermarkableInterface` answers two questions: `wantsWatermark()` — decided per **row**,
  a press photo being nobody's to sign — and `getWatermarkPosition()`. The signature itself is a site
  graphic under two roles, one for a light background and one for a dark one, and the watermarker
  measures the luminance of the very corner it is about to occupy to pick the readable one. Stamping
  happens **once per upload, on the highres**, every smaller size being cut from that already-signed
  image, so no size can carry a different signature and a double stamp is impossible by construction.
  Three `general` configs govern it, all expressed as a percentage of the image's own width.
- `Contract\VichPrivateFileInterface` plus `Service\PrivateFileResponseFactory::createDownloadResponse()`
  serve a paid file from outside `public/`. **It only builds the response — the access check stays your
  controller's job.**

## In the app

```twig
{{ site_media('logo') }}
<twig:c975LUi:Image:Link src="..." url="..." alt="..." width="150" height="150"/>
```

`Form\VichImageOptions::default($maxSize, $required)` is the one place the five Vich upload options
live, for an EasyAdmin `setFormTypeOptions()` and a plain `FormBuilder::add()` alike.

Two contributions worth knowing: `MediaUsageProviderInterface` tells the media library where a media
is used inside your own entities — without it the library cannot warn before a deletion — and
`PlaceholderMediaProviderInterface` offers stand-in images.

## Showing the progress of an upload

A form posting files holds the screen for the whole transfer plus everything the server then does with
it — fifty photos to resize and watermark is minutes of a page showing nothing, which reads as a click
that never registered and is clicked again. Two lines arm the bar:

```php
// In the form type
'attr' => $this->uploadProgress->formAttr(),

// In the controller receiving the submission, instead of $this->redirect($url)
return $this->uploadProgress->redirect($request, $url);
```

`Service\UploadProgress::formAttr()` **merges** with what the form already declares, a controller and
an action of its own kept. The second line is the one that cannot be skipped:
`XMLHttpRequest` follows a 302 by itself, so the arrival page — and the flash message it reads — would
be built inside a request nobody ever sees. `Service\UploadProgress::redirect()` answers an ordinary
submission with a real redirect, so nothing is duplicated for the two cases.

The bar itself is built by the `upload-progress` Stimulus controller: the transfer counted in
megabytes, then the processing, which has no percentage to give and is shown as an indeterminate
`<progress>`. Anything the server answers that is not that json is taken to be the form rendered again
with its errors, and swapped in place. The submit is taken away for the wait, handed back if the
network refuses the batch.

## Do not

- **Do not write an image resizer, a thumbnail generator or a Vich naming strategy.** Declare the
  three sizes and let the listener work.
- **Do not relate your media entity to this bundle's `Media`** — use the trait.
- **Do not crop a thumbnail square on disk.** Use `object-fit`.
- **Do not derive a thumbnail from the stored file** — the pipeline uses the original.
- **Do not build a filename from what the browser sent.**
- **Do not serve a private file directly**, and do not skip the access check because the factory
  built the response.
- **Do not forget to remove your own derivatives** when a row is deleted — nothing does it for you.
- **Do not upload the site logo or favicon as an entity of your own**; they are `Media` roles.
- **Do not write a progress bar of your own**, and do not redirect from a controller answering a form
  that carries one — hand the url over.
