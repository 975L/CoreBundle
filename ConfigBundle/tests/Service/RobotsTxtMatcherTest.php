<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\RobotsTxtMatcher;
use PHPUnit\Framework\TestCase;

class RobotsTxtMatcherTest extends TestCase
{
    public function testAllowsEverythingWithAnEmptyFile(): void
    {
        $this->assertTrue(RobotsTxtMatcher::for('')->allows('/pages/contact'));
    }

    // "Disallow:" with no value is how a file allows everything, and must not be read as disallowing the root
    public function testAnEmptyDisallowCarriesNoRule(): void
    {
        $matcher = RobotsTxtMatcher::for("User-agent: *\nDisallow:\n");

        $this->assertTrue($matcher->allows('/'));
        $this->assertTrue($matcher->allows('/pages/contact'));
    }

    public function testABlanketDisallowBlocksEveryPath(): void
    {
        $matcher = RobotsTxtMatcher::for("User-agent: *\nDisallow: /\n");

        $this->assertFalse($matcher->allows('/'));
        $this->assertFalse($matcher->allows('/pages/contact'));
    }

    // The prefix match a robots.txt rule is: "/admin" blocks everything under it and nothing beside it
    public function testAScopedDisallowOnlyBlocksItsOwnPrefix(): void
    {
        $matcher = RobotsTxtMatcher::for("User-agent: *\nDisallow: /admin\n");

        $this->assertFalse($matcher->allows('/admin'));
        $this->assertFalse($matcher->allows('/admin/users'));
        $this->assertTrue($matcher->allows('/pages/contact'));
    }

    // The longest matching rule decides, which is what lets a file carve one page out of a blocked folder
    public function testTheLongestMatchingRuleWins(): void
    {
        $matcher = RobotsTxtMatcher::for("User-agent: *\nDisallow: /pages/\nAllow: /pages/contact\n");

        $this->assertFalse($matcher->allows('/pages/legal'));
        $this->assertTrue($matcher->allows('/pages/contact'));
    }

    // Two rules of the same length contradict each other, and robots.txt resolves it in favour of crawling
    public function testAnAllowWinsATieAgainstADisallow(): void
    {
        $matcher = RobotsTxtMatcher::for("User-agent: *\nDisallow: /shop\nAllow: /shop\n");

        $this->assertTrue($matcher->allows('/shop'));
    }

    public function testAWildcardMatchesAnyRunOfCharacters(): void
    {
        $matcher = RobotsTxtMatcher::for("User-agent: *\nDisallow: /*.pdf\n");

        $this->assertFalse($matcher->allows('/files/report.pdf'));
        $this->assertTrue($matcher->allows('/files/report.html'));
    }

    // A trailing "$" anchors the pattern, so a path merely starting with it stays allowed
    public function testATrailingDollarAnchorsThePattern(): void
    {
        $matcher = RobotsTxtMatcher::for("User-agent: *\nDisallow: /shop$\n");

        $this->assertFalse($matcher->allows('/shop'));
        $this->assertTrue($matcher->allows('/shop/products/book'));
    }

    // A rule scoped to one crawler says nothing about the others - the very false positive a plain substring search on the file would produce
    public function testARuleScopedToAnotherCrawlerDoesNotApply(): void
    {
        $matcher = RobotsTxtMatcher::for("User-agent: SomeBot\nDisallow: /\n\nUser-agent: *\nDisallow:\n");

        $this->assertTrue($matcher->allows('/pages/contact'));
    }

    // A crawler the file names gets its own group rather than the wildcard one
    public function testTheGroupNamingTheCrawlerWinsOverTheWildcardOne(): void
    {
        $content = "User-agent: *\nDisallow: /\n\nUser-agent: Googlebot\nAllow: /\n";

        $this->assertTrue(RobotsTxtMatcher::for($content, 'Googlebot')->allows('/pages/contact'));
        $this->assertFalse(RobotsTxtMatcher::for($content)->allows('/pages/contact'));
    }

    // A group whose only rules are empty still exists, and still keeps its crawler out of the wildcard group - the "open to Googlebot, closed to everyone else" idiom
    public function testAGroupCarryingOnlyEmptyRulesStillWins(): void
    {
        $content = "User-agent: Googlebot\nDisallow:\n\nUser-agent: *\nDisallow: /\n";

        $this->assertTrue(RobotsTxtMatcher::for($content, 'Googlebot')->allows('/pages/contact'));
        $this->assertFalse(RobotsTxtMatcher::for($content)->allows('/pages/contact'));
    }

    // Consecutive User-agent lines share the rules that follow them
    public function testConsecutiveUserAgentsShareTheirRules(): void
    {
        $content = "User-agent: Googlebot\nUser-agent: Bingbot\nDisallow: /private\n";

        $this->assertFalse(RobotsTxtMatcher::for($content, 'Googlebot')->allows('/private'));
        $this->assertFalse(RobotsTxtMatcher::for($content, 'Bingbot')->allows('/private'));
    }

    // A User-agent line coming after a rule opens a new group instead of joining the previous one
    public function testARuleEndsTheCurrentGroup(): void
    {
        $content = "User-agent: Googlebot\nDisallow: /a\n\nUser-agent: Bingbot\nDisallow: /b\n";

        $matcher = RobotsTxtMatcher::for($content, 'Googlebot');
        $this->assertFalse($matcher->allows('/a'));
        $this->assertTrue($matcher->allows('/b'));
    }

    public function testCommentsAndCasingAreIgnored(): void
    {
        $matcher = RobotsTxtMatcher::for("# a comment\nUSER-AGENT: *\nDISALLOW: /admin # trailing comment\n");

        $this->assertFalse($matcher->allows('/admin'));
    }

    // Sitemap and Crawl-delay lines carry no rule, and must not be read as one
    public function testNonRuleFieldsAreIgnored(): void
    {
        $matcher = RobotsTxtMatcher::for("User-agent: *\nCrawl-delay: 10\nSitemap: https://example.com/sitemap-index.xml\n");

        $this->assertTrue($matcher->allows('/pages/contact'));
    }

    // A pattern is matched against a path, so a missing leading slash is a spelling rather than a rule that can never match
    public function testAPatternWithoutALeadingSlashStillMatches(): void
    {
        $matcher = RobotsTxtMatcher::for("User-agent: *\nDisallow: admin\n");

        $this->assertFalse($matcher->allows('/admin'));
    }

    // A crawler the file never names is bound by nothing at all
    public function testACrawlerNamedNowhereIsAllowedEverywhere(): void
    {
        $matcher = RobotsTxtMatcher::for("User-agent: SomeBot\nDisallow: /\n", 'Googlebot');

        $this->assertTrue($matcher->allows('/pages/contact'));
    }

    // The real robots.txt SeoFilesWriter produces: open to everyone, with the training crawlers blocked in groups of their own
    public function testTheBundlesOwnRobotsFileLeavesEveryPathCrawlable(): void
    {
        $content = "# Generated by c975l:seo:files:create\nUser-agent: *\nAllow: /\n\nUser-agent: ClaudeBot\nDisallow: /\n\nUser-agent: CCBot\nDisallow: /\n\nSitemap: https://example.com/sitemap-index.xml\n";

        $this->assertTrue(RobotsTxtMatcher::for($content)->allows('/pages/prestations'));
        $this->assertTrue(RobotsTxtMatcher::for($content, 'Googlebot')->allows('/pages/prestations'));
        $this->assertFalse(RobotsTxtMatcher::for($content, 'ClaudeBot')->allows('/pages/prestations'));
    }
}
