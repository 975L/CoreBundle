<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Test;

use c975L\ConfigBundle\Test\CodeRulesTestCase;
use c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Contract\RefundableInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\SkippedTest;

// The case itself, run over a fixture tree holding one good and one bad sample of each rule: every bad one is listed below, so the stale check proves it is caught, and the unlisted check proves no good one is
class CodeRulesTestCaseTest extends CodeRulesTestCase
{
    private const string FIXTURES = 'c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source';

    private ?array $allowedOverride = null;

    private ?string $sourceOverride = null;

    private ?string $refundableOverride = null;

    protected function sourceDirectory(): string
    {
        return $this->sourceOverride ?? __DIR__ . '/../Fixtures/CodeRules/Source';
    }

    protected function sourceNamespace(): string
    {
        return self::FIXTURES;
    }

    protected function refundableInterface(): string
    {
        return $this->refundableOverride ?? RefundableInterface::class;
    }

    protected function allowed(): array
    {
        return $this->allowedOverride ?? [
            self::STRING_LENGTH => [
                self::FIXTURES . '\Entity\Item::name' => 'fixture: no Length',
                self::FIXTURES . '\Entity\Item::code' => 'fixture: a Length longer than the column',
                self::FIXTURES . '\Entity\Trait\NamedTrait::nickname' => 'fixture: no Length, on a trait',
            ],
            self::BOUNDED_NUMBER => [
                self::FIXTURES . '\Entity\Item::stock' => 'fixture: no bound',
            ],
            self::PRE_FLUSH => [
                self::FIXTURES . '\Listener\ItemPreFlushListener' => 'fixture: preFlush',
            ],
            self::FORM_VALUE => [
                self::FIXTURES . '\Form\ItemType::stock' => 'fixture: data',
                self::FIXTURES . '\Form\ItemType::price' => 'fixture: attr value',
            ],
            self::EMPTY_DATA => [
                self::FIXTURES . '\Form\ItemType::name' => 'fixture: a setter refusing null',
            ],
            self::BATCH_DELETE => [
                self::FIXTURES . '\Controller\ItemCrudController' => 'fixture: batch delete left open',
            ],
            self::TEST_MODE => [
                self::FIXTURES . '\Basket\ItemBasketItemProvider' => 'fixture: test mode never read',
            ],
            self::REFUNDABLE => [
                self::FIXTURES . '\Basket\ItemBasketItemProvider' => 'fixture: no refund hook',
            ],
            self::RAW => [
                'item.html.twig: text|raw' => 'fixture: raw variable',
                'item.html.twig: title|trans|raw' => 'fixture: trans of a variable',
                "item.html.twig: 'label.catalog'|upper|raw" => 'fixture: a filter that escapes nothing',
            ],
            self::BULK_QUERY => [
                self::FIXTURES . '\Repository\ItemRepository::purge' => 'fixture: DQL delete',
                self::FIXTURES . '\Repository\ItemRepository::purgeRaw' => 'fixture: SQL delete',
            ],
        ];
    }

    // A finding nobody listed fails, naming it
    public function testAnUnlistedFindingFails(): void
    {
        $this->allowedOverride = [];

        $this->assertRuleFails('testNoListenerRunsOnPreFlush', 'ItemPreFlushListener (listens to preFlush)');
    }

    // An exception is justified or it is not one
    public function testAnExceptionWithoutAReasonFails(): void
    {
        $this->allowedOverride = [self::PRE_FLUSH => [self::FIXTURES . '\Listener\ItemPreFlushListener' => ' ']];

        $this->assertRuleFails('testNoListenerRunsOnPreFlush', 'allowed without a reason');
    }

    // An exception whose code went keeps the list truthful by failing
    public function testAStaleExceptionFails(): void
    {
        $this->allowedOverride = [self::PRE_FLUSH => [
            self::FIXTURES . '\Listener\ItemPreFlushListener' => 'fixture: preFlush',
            self::FIXTURES . '\Listener\GoneListener' => 'fixture: gone',
        ]];

        $this->assertRuleFails('testNoListenerRunsOnPreFlush', 'GoneListener (allowed but matches nothing any more');
    }

    public function testAMisspelledRuleFails(): void
    {
        $this->allowedOverride = ['pre_flsh' => ['Anything' => 'fixture: misspelled']];

        $this->assertRuleFails('testEveryAllowedExceptionNamesARule', 'pre_flsh');
    }

    // A bundle with no entity, no form nor template passes every rule, an assertion made each time
    public function testABundleWithNothingToInspectPasses(): void
    {
        $this->sourceOverride = __DIR__ . '/../Fixtures/CodeRules/Missing/src';
        $this->allowedOverride = [];
        $before = Assert::getCount();

        foreach ($this->ruleTests() as $test) {
            $this->{$test}();
        }

        $this->assertSame(\count($this->ruleTests()), Assert::getCount() - $before);
    }

    // PaymentBundle absent, the refund rule has nothing to check against
    public function testAMissingRefundHookSkips(): void
    {
        $this->refundableOverride = 'c975L\PaymentBundle\Contract\MissingInterface';

        try {
            $this->testEveryDeliveringBasketProviderIsRefundable();
        } catch (SkippedTest $skipped) {
            $this->assertStringContainsString('MissingInterface', $skipped->getMessage());

            return;
        }

        $this->fail('The refund rule ran without the interface it checks against');
    }

    // Runs one rule test, which has to fail with the expected words
    private function assertRuleFails(string $test, string $expected): void
    {
        try {
            $this->{$test}();
        } catch (ExpectationFailedException $failure) {
            $this->assertStringContainsString($expected, $failure->getMessage());

            return;
        }

        $this->fail(sprintf('%s passed where it should have failed on "%s"', $test, $expected));
    }

    // The rule tests the case ships, run one after the other
    /**
     * @return list<string>
     */
    private function ruleTests(): array
    {
        return array_values(array_filter(get_class_methods(CodeRulesTestCase::class), static fn (string $method) => str_starts_with($method, 'test')));
    }
}
