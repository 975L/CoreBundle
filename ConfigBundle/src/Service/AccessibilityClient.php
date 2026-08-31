<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

// Reads a page's rendered HTML for what the RGAA 4.1 can be answered from the markup alone (see AccessibilityHealthCheckProvider, which maps each finding below to its criterion). Nothing is judged here - which finding is a non-conformity and which is a doubt is the provider's call, this only reports what the page holds, the same division as ContentQualityClient.
//
// Only what a DOM can settle with no false positive: a criterion needing computed styles (contrast), a focus ring, a tab order or a human's judgement of relevance is not attempted at all rather than guessed at. That is a small share of the 106 criteria, and it is the honest share - a compliance report is worth exactly what its weakest line is worth
class AccessibilityClient
{
    // Offences kept per finding. The point is the offending markup pattern, which repeats: a template whose every card link is an unlabelled icon produces one fix and forty rows, and the count beside the samples still says how wide it spreads
    public const int MAX_OFFENCES = 10;

    // A link with no accessible name: no text of its own, no aria-label/aria-labelledby, no title, no image with an alt inside it, no <title> inside an inline svg. Absent and empty attributes are the same case here, normalize-space() answering '' for a missing one
    private const string LINK_WITHOUT_NAME = '//a[@href][not(normalize-space(.))][not(normalize-space(@aria-label))][not(@aria-labelledby)][not(normalize-space(@title))][not(.//img[normalize-space(@alt)])][not(.//*[local-name()="svg"]/*[local-name()="title"][normalize-space(.)])]';

    // The form fields carrying a label of their own. Buttons are left out - their name is their own content or value, which criterion 11.9 covers - and so are hidden fields, which nothing announces
    private const string FORM_FIELD = '//input[not(@type) or not(contains("|hidden|submit|reset|button|image|", concat("|", translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "|")))] | //select | //textarea';

    // A table holding rows but declaring no header at all, in either of the two forms criterion 5.6 accepts. It stays a doubt rather than a failure: a layout table legitimately has none - and is itself a non-conformity of 5.8, which no parser can tell apart from this one
    private const string TABLE_WITHOUT_HEADERS = '//table[.//tr][not(.//th)][not(.//*[@role="columnheader" or @role="rowheader"])]';

    // Well-formed language subtag, as criterion 8.4 asks - two or three letters, optionally followed by script/region/variant subtags. It says nothing about whether the code is the *right* one for the page's own words, which no parser can know
    private const string LANGUAGE_CODE = '/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/i';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // Fires the request and returns immediately - a caller checking many urls requests them all up front and reads them afterwards, so the HttpClient transport runs them concurrently instead of paying each timeout serially (same as ContentQualityClient::request())
    public function request(string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, ['timeout' => 30]);
    }

    /**
     * Blocks until the given in-flight response completes and reads it.
     *
     * @return array{language: string, languageIsWellFormed: bool, framesWithoutTitle: list<string>, linksWithoutName: list<string>, fieldsWithoutLabel: list<string>, headingJumps: list<string>, hasMainLandmark: bool, tablesWithoutHeaders: int}
     */
    public function read(ResponseInterface $response): array
    {
        $xpath = HtmlDocument::xpath($response->getContent());
        $language = trim((string) $xpath->query('//html/@lang')->item(0)?->nodeValue);

        return [
            'language' => $language,
            'languageIsWellFormed' => '' !== $language && 1 === preg_match(self::LANGUAGE_CODE, $language),
            'framesWithoutTitle' => $this->describe($xpath, '//iframe[not(normalize-space(@title))] | //frame[not(normalize-space(@title))]', 'src'),
            'linksWithoutName' => $this->describe($xpath, self::LINK_WITHOUT_NAME, 'href'),
            'fieldsWithoutLabel' => $this->extractFieldsWithoutLabel($xpath),
            'headingJumps' => $this->extractHeadingJumps($xpath),
            'hasMainLandmark' => $xpath->query('//main | //*[@role="main"]')->length > 0,
            'tablesWithoutHeaders' => $xpath->query(self::TABLE_WITHOUT_HEADERS)->length,
        ];
    }

    // Convenience for a single url - the same shape as read(), or throws on a network error
    public function analyze(string $url): array
    {
        return $this->read($this->request($url));
    }

    // The fields nothing names, criterion 11.1's own list of conditions taken one by one. The <label for> pass is done here rather than in the expression above because XPath 1.0 cannot compare an attribute against the node it is being tested on - the "for" values are collected once for the page, then each field's id is looked up in them
    private function extractFieldsWithoutLabel(\DOMXPath $xpath): array
    {
        $labelled = [];
        foreach (HtmlDocument::elements($xpath, '//label[normalize-space(@for)]') as $label) {
            $labelled[$label->getAttribute('for')] = true;
        }

        $offences = [];
        foreach (HtmlDocument::elements($xpath, self::FORM_FIELD) as $field) {
            $id = $field->getAttribute('id');
            if (isset($labelled[$id]) && '' !== $id) {
                continue;
            }

            if ('' !== trim($field->getAttribute('aria-label')) || '' !== trim($field->getAttribute('aria-labelledby')) || '' !== trim($field->getAttribute('title'))) {
                continue;
            }

            // A field wrapped in its own <label> is labelled without needing a "for" at all, and is the shape Symfony's form themes produce for a checkbox
            if (null !== $xpath->query('ancestor::label', $field)->item(0)) {
                continue;
            }

            $offences[] = $this->name($field, 'name');
        }

        return \array_slice(array_values(array_unique($offences)), 0, self::MAX_OFFENCES);
    }

    // The places where the heading levels skip one on the way down (an <h2> followed by an <h4>), which is what criterion 9.1 asks about and all a parser can answer of it. Only the <hx> tags: test 9.1.1 counts a role="heading" with an aria-level as a heading too, and reading those in would mean trusting an author-declared level to place a tag in the document's outline - it errs towards missing a jump rather than towards announcing one that isn't there. Going back *up* several levels is normal - a new section after a deep subsection - and is not reported. Whether a heading's wording is relevant, and how many <h1> a page carries, are somebody else's checks (see ContentQualityAnalyzer for the latter)
    private function extractHeadingJumps(\DOMXPath $xpath): array
    {
        $jumps = [];
        $previous = 0;

        foreach (HtmlDocument::elements($xpath, '//h1 | //h2 | //h3 | //h4 | //h5 | //h6') as $heading) {
            $level = (int) substr($heading->nodeName, 1);

            if (0 !== $previous && $level > $previous + 1) {
                $jumps[] = sprintf('<h%d> → <h%d>', $previous, $level);
            }

            $previous = $level;
        }

        return \array_slice(array_values(array_unique($jumps)), 0, self::MAX_OFFENCES);
    }

    // Each offending element named by the attribute that identifies it best, so the report says which one to go and fix rather than only how many there are. Deduped: the same unlabelled icon link repeated down a listing is one fix
    private function describe(\DOMXPath $xpath, string $expression, string $attribute): array
    {
        $offences = [];
        foreach (HtmlDocument::elements($xpath, $expression) as $element) {
            $offences[] = $this->name($element, $attribute);
        }

        return \array_slice(array_values(array_unique($offences)), 0, self::MAX_OFFENCES);
    }

    // "<a href=\"/contact\">" rather than the element's whole markup - enough to find it in a template, short enough to sit in a json column and in a report line
    private function name(\DOMElement $element, string $attribute): string
    {
        $value = trim($element->getAttribute($attribute));

        return '' === $value ? '<' . $element->nodeName . '>' : sprintf('<%s %s="%s">', $element->nodeName, $attribute, $value);
    }
}
