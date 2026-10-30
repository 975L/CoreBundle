<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\IndexNowClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

// Tells IndexNow which urls are new, changed or gone since the last accepted submission, so Bing and the other engines taking part recrawl them within minutes instead of at their next pass on the sitemap. Fed by the urls SitemapWriter just declared rather than by a listener in every bundle owning pages: whatever reaches a sitemap is announced, with nothing asked of the providers. A change is read off the "lastmod" a provider declares, the snapshot of the previous run being kept in var/
class IndexNowNotifier
{
    // Served at the root, so its scope covers every url of the site. A fixed name rather than "<key>.txt", declared to IndexNow through keyLocation, so the .gitignore rule and the removal stay a plain path (see ScaffoldInstaller)
    public const string KEY_FILE = 'indexnow-key.txt';

    // 32 hex chars, within the 8-128 the protocol accepts
    private const int KEY_LENGTH = 32;

    // The most IndexNow accepts in one request
    private const int BATCH_SIZE = 10000;

    private readonly string $keyFile;

    private readonly string $snapshotFile;

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly IndexNowClient $indexNowClient,
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
        #[Autowire(param: 'kernel.project_dir')]
        string $projectDir,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
        $this->keyFile = $projectDir . '/public/' . self::KEY_FILE;
        $this->snapshotFile = $projectDir . '/var/indexnow-snapshot.json';
        $this->filesystem = new Filesystem();
    }

    // Submits what changed among $declaredUrls (loc => declared lastmod, see SitemapWriter::getDeclaredUrls()) and returns how many urls went, or null when IndexNow doesn't apply to this site. Throws when IndexNow refuses or can't be reached, the snapshot being left as it was so the same urls go again on the next run
    public function notify(array $declaredUrls): ?int
    {
        // Only production speaks for the site: a dev copy shares its site-url, and would announce pages that don't exist online
        if ('prod' !== $this->environment) {
            return null;
        }

        $siteUrl = rtrim((string) $this->configService->get('site-url'), '/');
        $host = parse_url($siteUrl, PHP_URL_HOST);

        // Turned off, out of search engines, or no site-url to build the key location from: any key file left by a previous run goes, so nothing keeps vouching for submissions that no longer happen
        if (!is_string($host) || !$this->configService->get('seo-indexnow') || $this->configService->get('seo-robots-private')) {
            $this->filesystem->remove($this->keyFile);

            return null;
        }

        // IndexNow answers 422 to a whole batch holding one url of another host
        $current = array_filter(
            $declaredUrls,
            static fn (string $loc): bool => parse_url($loc, PHP_URL_HOST) === $host,
            ARRAY_FILTER_USE_KEY
        );

        $previous = $this->readSnapshot();
        $urls = $this->changedUrls($current, $previous);

        if ([] === $urls) {
            return 0;
        }

        // Written before the request, the engines fetching it to check the key the submission carries
        $key = $this->key();
        $this->filesystem->dumpFile($this->keyFile, $key);

        foreach (array_chunk($urls, self::BATCH_SIZE) as $batch) {
            $this->indexNowClient->submit($host, $key, $siteUrl . '/' . self::KEY_FILE, $batch);
        }

        $this->filesystem->dumpFile($this->snapshotFile, json_encode($current, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return count($urls);
    }

    // New urls, urls whose lastmod moved, and urls gone from the sitemaps - IndexNow wants those too, the engines then finding the 404 or 410 sooner. A url declaring no lastmod only counts when it is new: SitemapWriter dates it from the day it runs, and comparing that would resubmit it every night
    private function changedUrls(array $current, array $previous): array
    {
        $changed = array_keys(array_filter(
            $current,
            static fn (?string $lastmod, string $loc): bool => !array_key_exists($loc, $previous) || (null !== $lastmod && $lastmod !== $previous[$loc]),
            ARRAY_FILTER_USE_BOTH
        ));

        return array_merge($changed, array_keys(array_diff_key($previous, $current)));
    }

    // The urls accepted on the last run, [] on the first one - every url then goes, which is what a site newly announcing itself wants
    private function readSnapshot(): array
    {
        $snapshot = is_file($this->snapshotFile) ? json_decode((string) file_get_contents($this->snapshotFile), true) : null;

        return is_array($snapshot) ? $snapshot : [];
    }

    // Derived from the application secret rather than stored: stable from one run to the next, nothing to configure, and public by design since the key file is served to anyone
    private function key(): string
    {
        return substr(hash_hmac('sha256', 'indexnow', $this->secret), 0, self::KEY_LENGTH);
    }
}
