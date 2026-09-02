<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\ContentOffenceLocatorRegistry;
use c975L\ConfigBundle\Management\ContentQualityAnalyzer;
use c975L\ConfigBundle\Management\ExternalLinkCheckSchedule;
use c975L\ConfigBundle\Service\ContentQualityClient;
use c975L\ConfigBundle\Service\UrlStatusChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContentQualityAnalyzerTest extends TestCase
{
    private const array GOOD_ANALYSIS = [
        'title' => 'Un titre de page tout à fait correct',
        'description' => 'Une description de test suffisamment longue pour passer le seuil minimal recommandé.',
        'hasDescription' => true,
        'h1Count' => 1,
        'imagesWithoutAlt' => [],
        'socialTags' => ['og:title' => 'T', 'og:description' => 'D', 'og:image' => '/media/og.png'],
        'internalLinks' => [],
        'externalLinks' => [],
        'linkTexts' => [],
        'robots' => [],
    ];

    // 'indexable' is what turns the noindex check on, and is left out unless a test declares it - exactly like a caller listing every page it holds rather than the ones it declares to search engines
    private function entry(string $url, ?object $source = null, bool $indexable = false): array
    {
        return ['url' => $url, 'label' => $url, 'editUrl' => null, 'source' => $source] + ($indexable ? ['indexable' => true] : []);
    }

    // Each label rendered as its own translation id with the parameters substituted, so a test asserts on what the row really says
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = []) => strtr($id, $parameters)
        );

        return $translator;
    }

    private function createResponse(int $statusCode = 200, int $redirectCount = 0, string $finalUrl = ''): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getInfo')->willReturnCallback(
            static fn (string $key) => 'redirect_count' === $key ? $redirectCount : ('url' === $key ? $finalUrl : null)
        );

        return $response;
    }

    private function createAnalyzer(
        ContentQualityClient $client,
        ?UrlStatusChecker $checker = null,
        ?ContentOffenceLocatorRegistry $registry = null,
        ?ExternalLinkCheckSchedule $schedule = null,
    ): ContentQualityAnalyzer {
        return new ContentQualityAnalyzer(
            $client,
            $checker ?? $this->createStub(UrlStatusChecker::class),
            $registry ?? new ContentOffenceLocatorRegistry(),
            $this->createTranslator(),
            $schedule ?? $this->createSchedule(true),
        );
    }

    // The external link pass is due unless a test says otherwise - the run of a url never checked before, which is what every other test here describes
    private function createSchedule(bool $due, array $broken = []): ExternalLinkCheckSchedule
    {
        $schedule = $this->createStub(ExternalLinkCheckSchedule::class);
        $schedule->method('decide')->willReturn(['due' => $due, 'checkedAt' => '2026-08-25T18:00:00+02:00', 'broken' => $broken]);

        return $schedule;
    }

    // read() answers the canonical of whichever url it was handed, so a clean analysis stays clean for every url a test declares - a page claiming another url as its canonical is what a test sets up on purpose
    private function readsCleanly(array $analysis = self::GOOD_ANALYSIS): callable
    {
        return static fn (ResponseInterface $response, string $url): array => $analysis + ['canonical' => $url];
    }

    // Every url answering the same clean analysis, with no link to check
    private function createHealthyClient(array $analysis = self::GOOD_ANALYSIS): ContentQualityClient
    {
        $client = $this->createStub(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse());
        $client->method('read')->willReturnCallback($this->readsCleanly($analysis));

        return $client;
    }

    public function testAnalyzeReturnsNoRowForAnEmptyList(): void
    {
        $this->assertSame([], $this->createAnalyzer($this->createHealthyClient())->analyze([]));
    }

    public function testAnalyzeReturnsOneOkRowPerCleanUrl(): void
    {
        $rows = $this->createAnalyzer($this->createHealthyClient())->analyze([
            $this->entry('https://example.com/one'),
            $this->entry('https://example.com/two'),
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('https://example.com/one', $rows[0]['url']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[1]['status']);
    }

    // Rows are keyed by the entry's own position and sorted back at the end, so a url failing in the middle doesn't push every following row to the bottom
    public function testAnalyzeKeepsTheDeclaredOrderWhenAUrlInTheMiddleFails(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $client->method('request')->willReturnCallback(function (string $url) {
            if (str_contains($url, 'two')) {
                throw new \RuntimeException('Connection refused');
            }

            return $this->createResponse();
        });
        $client->method('read')->willReturnCallback($this->readsCleanly());

        $rows = $this->createAnalyzer($client)->analyze([
            $this->entry('https://example.com/one'),
            $this->entry('https://example.com/two'),
            $this->entry('https://example.com/three'),
        ]);

        $this->assertSame(
            ['https://example.com/one', 'https://example.com/two', 'https://example.com/three'],
            array_column($rows, 'url')
        );
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[1]['status']);
        $this->assertSame(['error' => 'Connection refused'], $rows[1]['details']);
    }

    // An entry with a source gets a HEAD first: what it answered is reported as such, instead of as a raw analysis failure
    public function testAnalyzeReportsAnEntryWhoseHeadAnswers404WithoutAnalyzingIt(): void
    {
        $checker = $this->createStub(UrlStatusChecker::class);
        $checker->method('status')->willReturn(404);

        $client = $this->createMock(ContentQualityClient::class);
        $client->expects($this->never())->method('request');

        $rows = $this->createAnalyzer($client, $checker)->analyze([$this->entry('https://example.com/gone', new \stdClass())]);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('label.health_check_url_not_found', $rows[0]['summary']);
    }

    // A url declared gone answers correctly, what's left to fix is that something still declares it
    public function testAnalyzeWarnsRatherThanErrorsOnA410(): void
    {
        $checker = $this->createStub(UrlStatusChecker::class);
        $checker->method('status')->willReturn(410);

        $rows = $this->createAnalyzer($this->createHealthyClient(), $checker)->analyze([$this->entry('https://example.com/removed', new \stdClass())]);

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
        $this->assertSame('label.health_check_url_gone', $rows[0]['summary']);
    }

    // A status describing how the server treats this checker, not whether the page is fine - a site behind a WAF would otherwise turn every one of its pages red
    public function testAnalyzeWarnsOnAnInconclusiveStatus(): void
    {
        $checker = $this->createStub(UrlStatusChecker::class);
        $checker->method('status')->willReturn(403);

        $rows = $this->createAnalyzer($this->createHealthyClient(), $checker)->analyze([$this->entry('https://example.com/walled', new \stdClass())]);

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
        $this->assertSame('label.health_check_url_inconclusive', $rows[0]['summary']);
    }

    // A bare declared url gets its verdict from its own analysis response, the HEAD being skipped to avoid doubling the request count of a bundle declaring thousands of urls
    public function testAnalyzeSkipsTheHeadForAnEntryWithoutASource(): void
    {
        $checker = $this->createMock(UrlStatusChecker::class);
        $checker->expects($this->never())->method('status');

        $rows = $this->createAnalyzer($this->createHealthyClient(), $checker)->analyze([$this->entry('https://example.com/declared')]);

        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    // A declared url is meant to be the canonical one and answer 200 directly - the hop is read off the analysis response itself, no second request
    public function testAnalyzeReportsTheRedirectsTheAnalysisRequestFollowed(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse(redirectCount: 1, finalUrl: 'https://example.com/final'));
        $client->method('read')->willReturnCallback($this->readsCleanly(['canonical' => 'https://example.com/final'] + self::GOOD_ANALYSIS));

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/old')]);

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
        // The hop alone: the content answering at the end of it belongs to the url it ended on, so its canonical is not a second defect
        $this->assertSame('label.health_check_content_quality_redirects', $rows[0]['summary']);
        $this->assertNull($rows[0]['details']['canonicalIssue']);
        $this->assertSame(['count' => 1, 'finalUrl' => 'https://example.com/final'], $rows[0]['details']['redirect']);
    }

    // Internal and external links go through one shared pass, deduped: two pages linking to the same url check it once
    public function testAnalyzeChecksEachDistinctLinkOnlyOnce(): void
    {
        $analysis = ['internalLinks' => ['https://example.com/shared'], 'externalLinks' => []] + self::GOOD_ANALYSIS;

        $client = $this->createMock(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse());
        $client->method('read')->willReturnCallback($this->readsCleanly($analysis));
        $client->expects($this->once())->method('requestLinkCheck')->willReturn($this->createResponse());
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_OK);

        $rows = $this->createAnalyzer($client)->analyze([
            $this->entry('https://example.com/one'),
            $this->entry('https://example.com/two'),
        ]);

        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    // A dead link on the site's own pages is an error, a dead external one only a warning: it isn't yours to fix on your own schedule
    public function testAnalyzeErrorsOnABrokenInternalLinkAndWarnsOnAnExternalOne(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse());
        $client->method('read')->willReturnCallback(
            fn (ResponseInterface $response, string $url): array => ['canonical' => $url] + (str_contains($url, 'one')
                ? ['internalLinks' => ['https://example.com/dead'], 'linkTexts' => ['https://example.com/dead' => 'Nos tarifs']] + self::GOOD_ANALYSIS
                : ['externalLinks' => ['https://elsewhere.example/dead']] + self::GOOD_ANALYSIS)
        );
        $client->method('requestLinkCheck')->willReturn($this->createResponse());
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_BROKEN);

        $rows = $this->createAnalyzer($client)->analyze([
            $this->entry('https://example.com/one'),
            $this->entry('https://example.com/two'),
        ]);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('Nos tarifs', $rows[0]['details']['brokenLinks'][0]['text']);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[1]['status']);
        $this->assertSame([], $rows[1]['details']['brokenLinks']);
        $this->assertCount(1, $rows[1]['details']['brokenExternalLinks']);
    }

    // Ten requests fired at once at whichever host comes up ten times in a row is what gets a server's IP rate limited, then blocked - external links are spread over their hosts, at most one per batch, so the same host is never called twice concurrently
    public function testAnalyzeSpreadsExternalLinkChecksOverTheirHosts(): void
    {
        $calls = [];

        $client = $this->createStub(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse());
        $client->method('read')->willReturnCallback($this->readsCleanly(['externalLinks' => ['https://shop.example/a', 'https://shop.example/b', 'https://other.example/c']] + self::GOOD_ANALYSIS));
        $client->method('requestLinkCheck')->willReturnCallback(function (string $url) use (&$calls): ResponseInterface {
            $calls[] = $url;

            return $this->createResponse();
        });
        $client->method('readLinkCheck')->willReturnCallback(function () use (&$calls): string {
            $calls[] = 'read';

            return ContentQualityClient::LINK_OK;
        });

        $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        // The first batch takes one url per host, the second the shop's remaining one - the two shop urls are never in flight together
        $this->assertSame(
            ['https://shop.example/a', 'https://other.example/c', 'read', 'read', 'https://shop.example/b', 'read'],
            $calls
        );
    }

    // In between two monthly passes the external links are not called at all, and what the last pass found is reported back - the dashboard keeps showing a dead external link without its host being called every week
    public function testAnalyzeReportsBackTheExternalVerdictsOfAPassNotDueAgain(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse());
        $client->method('read')->willReturnCallback($this->readsCleanly(['externalLinks' => ['https://shop.example/gone', 'https://shop.example/live']] + self::GOOD_ANALYSIS));
        $client->expects($this->never())->method('requestLinkCheck');
        $client->expects($this->never())->method('requestLinkCheckFallback');

        $schedule = $this->createSchedule(false, ['https://shop.example/gone' => true]);
        $rows = $this->createAnalyzer($client, null, null, $schedule)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
        $this->assertSame('https://shop.example/gone', $rows[0]['details']['brokenExternalLinks'][0]['url']);
        // The previous date is carried into the new row, otherwise the next run would find none and call the links again
        $this->assertSame('2026-08-25T18:00:00+02:00', $rows[0]['details'][ExternalLinkCheckSchedule::CHECKED_AT_KEY]);
    }

    // The site's own pages are checked every run whatever the external links do - they are our own server, and a dead internal link is ours to fix now
    public function testAnalyzeStillChecksInternalLinksWhenTheExternalPassIsNotDue(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse());
        $client->method('read')->willReturnCallback($this->readsCleanly(['internalLinks' => ['https://example.com/gone'], 'externalLinks' => ['https://shop.example/a']] + self::GOOD_ANALYSIS));
        $client->expects($this->once())->method('requestLinkCheck')->with('https://example.com/gone')->willReturn($this->createResponse(404));
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_BROKEN);

        $rows = $this->createAnalyzer($client, null, null, $this->createSchedule(false))->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('https://example.com/gone', $rows[0]['details']['brokenLinks'][0]['url']);
    }

    // A host answering that it filters this client is not called a second time: the GET would learn nothing, and insisting is how a server's IP ends up blocked
    public function testAnalyzeDoesNotRetryALinkWhoseHostFiltersTheChecker(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse());
        $client->method('read')->willReturnCallback($this->readsCleanly(['externalLinks' => ['https://shop.example/book']] + self::GOOD_ANALYSIS));
        $client->method('requestLinkCheck')->willReturn($this->createResponse(403));
        $client->expects($this->never())->method('requestLinkCheckFallback');
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_FILTERED);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        // Filtered is not broken: the link stays out of the list, exactly like a still unknown one
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
        $this->assertSame([], $rows[0]['details']['brokenExternalLinks']);
    }

    // A link the HEAD pass couldn't conclude on is retried once in GET, so only a real answer ever ends up reported as broken
    public function testAnalyzeRetriesAnInconclusiveLinkBeforeCallingItBroken(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse());
        $client->method('read')->willReturnCallback($this->readsCleanly(['internalLinks' => ['https://example.com/maybe']] + self::GOOD_ANALYSIS));
        $client->method('requestLinkCheck')->willReturn($this->createResponse());
        $client->expects($this->once())->method('requestLinkCheckFallback')->willReturn($this->createResponse());
        $client->method('readLinkCheck')->willReturnOnConsecutiveCalls(ContentQualityClient::LINK_UNKNOWN, ContentQualityClient::LINK_OK);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    // A HEAD answering 404 is retried too, and the GET decides: a share intent, a single-page app's route (bsky.app/intent/compose) answers 404 to a HEAD it serves in 200, which had a working share button reported as a dead link
    public function testAnalyzeRetriesALinkTheHeadCalledBrokenBeforeReportingIt(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse());
        $client->method('read')->willReturnCallback($this->readsCleanly(['externalLinks' => ['https://bsky.app/intent/compose?text=x']] + self::GOOD_ANALYSIS));
        $client->method('requestLinkCheck')->willReturn($this->createResponse(404));
        $client->expects($this->once())->method('requestLinkCheckFallback')->with('https://bsky.app/intent/compose?text=x')->willReturn($this->createResponse());
        $client->method('readLinkCheck')->willReturnOnConsecutiveCalls(ContentQualityClient::LINK_BROKEN, ContentQualityClient::LINK_OK);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
        $this->assertSame([], $rows[0]['details']['brokenExternalLinks']);
    }

    // The GET confirming the HEAD's verdict is what makes a link broken - the retry only ever removes a false positive, never a real one
    public function testAnalyzeReportsALinkBrokenWhenTheGetRetryConfirmsIt(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse());
        $client->method('read')->willReturnCallback($this->readsCleanly(['internalLinks' => ['https://example.com/gone']] + self::GOOD_ANALYSIS));
        $client->method('requestLinkCheck')->willReturn($this->createResponse(404));
        $client->expects($this->once())->method('requestLinkCheckFallback')->willReturn($this->createResponse(404));
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_BROKEN);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('https://example.com/gone', $rows[0]['details']['brokenLinks'][0]['url']);
    }

    // A link still unknown after both passes stays out of the broken list: a timeout of the run's own making says nothing about the link
    public function testAnalyzeLeavesAStillUnknownLinkOutOfTheBrokenList(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $client->method('request')->willReturn($this->createResponse());
        $client->method('read')->willReturnCallback($this->readsCleanly(['internalLinks' => ['https://example.com/maybe']] + self::GOOD_ANALYSIS));
        $client->method('requestLinkCheck')->willReturn($this->createResponse());
        $client->method('requestLinkCheckFallback')->willReturn($this->createResponse());
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_UNKNOWN);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
        $this->assertSame([], $rows[0]['details']['brokenLinks']);
    }

    // An offence is traced back to the screen holding it whenever a locator recognizes the entry's source
    public function testAnalyzeLinksAnImageWithoutAltToTheScreenHoldingIt(): void
    {
        $registry = $this->createStub(ContentOffenceLocatorRegistry::class);
        $registry->method('locateImage')->willReturn(['label' => 'Hero block', 'editUrl' => '/admin/block/12']);

        $checker = $this->createStub(UrlStatusChecker::class);
        $checker->method('status')->willReturn(200);

        $client = $this->createHealthyClient(['imagesWithoutAlt' => ['/media/hero.png']] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client, $checker, $registry)->analyze([$this->entry('https://example.com/one', new \stdClass())]);

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
        $this->assertSame(
            ['src' => '/media/hero.png', 'block' => 'Hero block', 'editUrl' => '/admin/block/12'],
            $rows[0]['details']['imagesWithoutAlt'][0]
        );
    }

    // Without any locator the offence is still reported, just unlinked
    public function testAnalyzeStillReportsAnUnclaimedImageWithoutAlt(): void
    {
        $client = $this->createHealthyClient(['imagesWithoutAlt' => ['/media/logo.png']] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame(
            ['src' => '/media/logo.png', 'block' => null, 'editUrl' => null],
            $rows[0]['details']['imagesWithoutAlt'][0]
        );
    }

    // An empty title and an empty description are reported as the fields to fill, not also as the share tags they didn't produce
    public function testAnalyzeDoesNotReportASocialTagTwiceWhenItsOwnFieldIsAlreadyMissing(): void
    {
        $client = $this->createHealthyClient([
            'title' => '',
            'description' => '',
            'hasDescription' => false,
            'socialTags' => ['og:image' => '/media/og.png'],
        ] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame([], $rows[0]['details']['missingSocialTags']);
        $this->assertSame('missing', $rows[0]['details']['titleIssue']);
        $this->assertFalse($rows[0]['details']['hasDescription']);
    }

    // The share tag is genuinely missing from the template when the field it mirrors is filled
    public function testAnalyzeReportsASocialTagMissingWhileItsFieldIsFilled(): void
    {
        $client = $this->createHealthyClient(['socialTags' => ['og:title' => 'T', 'og:description' => 'D']] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame(['og:image'], $rows[0]['details']['missingSocialTags']);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
    }

    public function testAnalyzeReportsATitleShorterThanTheRecommendedWindow(): void
    {
        $client = $this->createHealthyClient(['title' => 'Court'] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame('short', $rows[0]['details']['titleIssue']);
        $this->assertSame(5, $rows[0]['details']['titleLength']);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
    }

    public function testAnalyzeReportsATitleLongerThanTheRecommendedWindow(): void
    {
        $client = $this->createHealthyClient(['title' => str_repeat('a', ContentQualityAnalyzer::TITLE_MAX_LENGTH + 1)] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame('long', $rows[0]['details']['titleIssue']);
    }

    // A missing H1 and several H1 are two different things to say
    public function testAnalyzeReportsBothAMissingAndADuplicatedH1(): void
    {
        $none = $this->createAnalyzer($this->createHealthyClient(['h1Count' => 0] + self::GOOD_ANALYSIS))
            ->analyze([$this->entry('https://example.com/one')]);
        $several = $this->createAnalyzer($this->createHealthyClient(['h1Count' => 3] + self::GOOD_ANALYSIS))
            ->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame('label.health_check_content_quality_no_h1', $none[0]['summary']);
        $this->assertSame('label.health_check_content_quality_several_h1', $several[0]['summary']);
        $this->assertSame(3, $several[0]['details']['h1Count']);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $several[0]['status']);
    }

    // A canonical naming another url hands the whole page over to it, and Search Console reports this one as a duplicate
    public function testAnalyzeReportsAPageDeclaringAnotherUrlAsCanonical(): void
    {
        $client = $this->createHealthyClient(['canonical' => 'https://example.com/other'] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame('mismatch', $rows[0]['details']['canonicalIssue']);
        $this->assertSame('label.health_check_content_quality_canonical_mismatch', $rows[0]['summary']);
        $this->assertSame('https://example.com/other', $rows[0]['details']['canonical']);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
    }

    // What a theme overriding layout.html.twig without carrying canonical_url() over leaves behind
    public function testAnalyzeReportsAPageDeclaringNoCanonicalAtAll(): void
    {
        $client = $this->createHealthyClient(['canonical' => ''] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertSame('missing', $rows[0]['details']['canonicalIssue']);
        $this->assertSame('label.health_check_content_quality_no_canonical', $rows[0]['summary']);
    }

    // The site root is declared without a trailing slash by the sitemap and with one by canonical_url(): both name the home page, and neither is a duplicate of the other
    public function testAnalyzeAcceptsACanonicalDifferingOnlyByATrailingSlash(): void
    {
        $client = $this->createHealthyClient(['canonical' => 'https://EXAMPLE.com/'] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com')]);

        $this->assertNull($rows[0]['details']['canonicalIssue']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    // A url its own site declares to search engines while the page tells them to drop it: the page is simply not in the results
    public function testAnalyzeErrorsOnANoindexDeclaredUrl(): void
    {
        $client = $this->createHealthyClient(['robots' => ['noindex', 'follow']] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one', indexable: true)]);

        $this->assertTrue($rows[0]['details']['noindex']);
        $this->assertSame('label.health_check_content_quality_noindex', $rows[0]['summary']);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
    }

    // "none" is the shorthand for "noindex, nofollow" and keeps the page out of the results just as much
    public function testAnalyzeReadsNoneAsANoindex(): void
    {
        $client = $this->createHealthyClient(['robots' => ['none']] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one', indexable: true)]);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
    }

    // A caller listing every page it holds passes pages that are meant to carry a noindex - reporting those would leave them red forever with nothing to fix
    public function testAnalyzeIgnoresANoindexOnAUrlNothingDeclaresAsIndexable(): void
    {
        $client = $this->createHealthyClient(['robots' => ['noindex', 'follow']] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/account')]);

        $this->assertFalse($rows[0]['details']['noindex']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    // Every clause of a page carrying several offences ends up in the one summary
    public function testAnalyzeJoinsEveryIssueIntoTheRowSummary(): void
    {
        $client = $this->createHealthyClient(['title' => '', 'h1Count' => 0] + self::GOOD_ANALYSIS);

        $rows = $this->createAnalyzer($client)->analyze([$this->entry('https://example.com/one')]);

        $this->assertStringContainsString('label.health_check_content_quality_no_title', $rows[0]['summary']);
        $this->assertStringContainsString('label.health_check_content_quality_no_h1', $rows[0]['summary']);
        $this->assertStringContainsString(' · ', $rows[0]['summary']);
    }

    // More entries than one batch holds, so the batching itself is exercised rather than assumed
    public function testAnalyzeReturnsARowForEveryEntryBeyondOneBatch(): void
    {
        $entries = array_map(fn (int $i) => $this->entry('https://example.com/page-' . $i), range(1, 25));

        $rows = $this->createAnalyzer($this->createHealthyClient())->analyze($entries);

        $this->assertCount(25, $rows);
        $this->assertSame('https://example.com/page-25', $rows[24]['url']);
    }
}
