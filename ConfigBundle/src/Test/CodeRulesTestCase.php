<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Test;

use Doctrine\ORM\Mapping\Column;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;

// The defects the audit of 11/11/2026 found in bundle after bundle, each made a failing test: extend this in one class of the bundle's tests/ giving its src/ directory and namespace (see the README, "CodeRulesTestCase"). No kernel and no database, the code is read by reflection and by its tokens; shipped in src/ like ManagementTargetsTestCase, so a bundle can autoload it, and excluded from the service scan
abstract class CodeRulesTestCase extends TestCase
{
    public const string STRING_LENGTH = 'string_length';
    public const string BOUNDED_NUMBER = 'bounded_number';
    public const string PRE_FLUSH = 'pre_flush';
    public const string FORM_VALUE = 'form_value';
    public const string EMPTY_DATA = 'empty_data';
    public const string BATCH_DELETE = 'batch_delete';
    public const string TEST_MODE = 'test_mode';
    public const string REFUNDABLE = 'refundable';
    public const string RAW = 'raw';
    public const string BULK_QUERY = 'bulk_query';

    // The filters whose output may be printed raw, the one written right before "raw" deciding: sanitize_html cleaned it, json_encode escaped it, trans of a quoted key is interface text from the xlf
    public const array RAW_SAFE_FILTERS = ['sanitize_html', 'json_encode', 'trans'];

    // The text fields whose empty submission reaches the setter as null
    private const array TEXT_TYPES = ['TextType', 'TextareaType', 'EmailType', 'UrlType', 'TelType', 'SearchType'];

    // The bundle's own src/ directory, every rule reading the classes below it
    abstract protected function sourceDirectory(): string;

    // The namespace mapped onto sourceDirectory(), a class being named after its path
    abstract protected function sourceNamespace(): string;

    // The templates read by the "raw" rule, next to src/ by default
    protected function templatesDirectory(): string
    {
        return dirname(rtrim($this->sourceDirectory(), '/')) . '/templates';
    }

    // The named and justified exceptions, rule by rule: a reason is mandatory, and an exception matching nothing fails as stale
    /**
     * @return array<string, array<string, string>> rule => [identifier => reason]
     */
    protected function allowed(): array
    {
        return [];
    }

    // The endings of an int or float property holding money, a stock, a rate or a quantity, matched case-insensitively ("limitedQuantity", "vatRate")
    /**
     * @return list<string>
     */
    protected function boundedPropertyNames(): array
    {
        return ['price', 'amount', 'vat', 'quantity', 'stock', 'weight', 'rate', 'value', 'cost', 'fee', 'total', 'discount', 'credits'];
    }

    // The interface a delivering basket provider implements, named as a string: PaymentBundle is not a dependency of every bundle
    protected function refundableInterface(): string
    {
        return 'c975L\PaymentBundle\Contract\RefundableBasketItemProviderInterface';
    }

    // An Assert\Length(max) no longer than the column, or the database cuts (or refuses) what the form accepted
    public function testEveryStringColumnCarriesALengthWithinItsColumn(): void
    {
        $found = [];

        foreach ($this->entityProperties() as $id => $property) {
            $column = $this->column($property);
            if (null === $column || null !== $column->enumType || 'string' !== ($column->type ?? $this->phpType($property))) {
                continue;
            }

            $max = $this->lengthMax($property);
            $length = $column->length ?? 255;
            if (null === $max || $max > $length) {
                $found[$id] = sprintf('Length(max: %s) for a column of %d', $max ?? 'none', $length);
            }
        }

        $this->assertRule(self::STRING_LENGTH, $found, 'Each of these string columns needs an Assert\Length(max) no longer than its column');
    }

    // Accepted bounds: Positive, PositiveOrZero, GreaterThan, GreaterThanOrEqual and Range - a negative price or stock is decided, not left to chance
    public function testEveryAmountCarriesABound(): void
    {
        $found = [];

        foreach ($this->entityProperties() as $id => $property) {
            if (null === $this->column($property) || !\in_array($this->phpType($property), ['int', 'float'], true) || !$this->isBoundedName($property->getName())) {
                continue;
            }

            $bounded = array_filter($this->constraints($property), static fn (Constraint $constraint) => $constraint instanceof GreaterThan || $constraint instanceof GreaterThanOrEqual || $constraint instanceof Range);
            if ([] === $bounded) {
                $found[$id] = 'no lower bound';
            }
        }

        $this->assertRule(self::BOUNDED_NUMBER, $found, 'Each of these amounts needs a bound (PositiveOrZero, Positive, Range, GreaterThan...)');
    }

    // A preFlush listener runs on every flush, the visitors' ones included: prePersist and preUpdate on a real change set instead
    public function testNoListenerRunsOnPreFlush(): void
    {
        $found = [];

        foreach ($this->sourceFiles($this->sourceDirectory()) as $file) {
            if (preg_match('/Events::preFlush|[\'"]preFlush[\'"]|ORM\\\\PreFlush\b/', $this->code($file))) { // rules-ok: the pattern this rule looks for
                $found[$this->className($file)] = 'listens to preFlush';
            }
        }

        $this->assertRule(self::PRE_FLUSH, $found, 'Each of these classes listens to preFlush');
    }

    // "data" or attr "value" replaces the saved value on every edit: the default goes on the entity or in a PRE_SET_DATA listener. A type declaring "data_class" => null builds fresh data, with no saved value to replace
    public function testNoMappedFormFieldOverridesItsValue(): void
    {
        $found = [];

        foreach ($this->formFiles() as $file => $code) {
            if (preg_match('/\'data_class\'\s*=>\s*null\b/', $code)) {
                continue;
            }

            foreach ($this->addedFields($file) as $field => $options) {
                if (!$this->isMapped($options)) {
                    continue;
                }

                if (preg_match('/\'data\'\s*=>/', $options) || preg_match('/\'attr\'\s*=>\s*\[[^\]]*\'value\'\s*=>/', $options)) {
                    $found[$this->className($file) . '::' . $field] = 'data or attr value on a mapped field';
                }
            }
        }

        $this->assertRule(self::FORM_VALUE, $found, 'Each of these mapped fields overrides the saved value');
    }

    // An emptied text field hands null to its setter: a setter refusing null needs "empty_data" => '' (or a nullable parameter)
    public function testEveryTextFieldBoundToANonNullableSetterCarriesEmptyData(): void
    {
        $found = [];

        foreach (array_keys($this->formFiles()) as $file) {
            $dataClass = $this->dataClass((string) file_get_contents($file));
            if (null === $dataClass) {
                continue;
            }

            foreach ($this->addedFields($file, true) as $field => $options) {
                if ($this->isMapped($options) && !preg_match('/\'(?:empty_data|property_path)\'\s*=>/', $options) && $this->refusesNull($dataClass, $field)) {
                    $found[$this->className($file) . '::' . $field] = sprintf('%s::set%s() refuses null', $dataClass, ucfirst($field));
                }
            }
        }

        $this->assertRule(self::EMPTY_DATA, $found, 'Each of these text fields needs "empty_data" (or a nullable setter)');
    }

    // EasyAdmin's batch delete calls deleteEntity() directly, past any guard written in delete()
    public function testEveryCrudControllerGuardingDeleteGuardsItsBatchDelete(): void
    {
        $found = [];

        foreach ($this->sourceFiles($this->sourceDirectory(), 'CrudController.php') as $file) {
            $class = $this->className($file);
            if (!class_exists($class) || !is_subclass_of($class, AbstractCrudController::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if (!$this->overrides($reflection, 'delete') || $this->overrides($reflection, 'deleteEntity')) {
                continue;
            }

            if (!preg_match('/->(?:disable|remove)\([^;]*Action::BATCH_DELETE/', $this->code($file))) {
                $found[$class] = 'guards delete() only';
            }
        }

        $this->assertRule(self::BATCH_DELETE, $found, 'Each of these CRUD controllers guards delete() but leaves BATCH_DELETE open: disable it or guard deleteEntity()');
    }

    // A basket paid in test mode never produces anything real: an empty onBasketPaid() delivers nothing, any other one not concerned is listed in allowed()
    public function testEveryPaidBasketReadsTheTestMode(): void
    {
        $found = [];

        foreach ($this->deliveringBodies() as $class => $body) {
            if (!str_contains($body, 'isTestMode(')) {
                $found[$class] = 'onBasketPaid() never reads isTestMode()';
            }
        }

        $this->assertRule(self::TEST_MODE, $found, 'Each of these providers delivers in test mode as in production');
    }

    // What onBasketPaid() hands over is taken back at refund - skipped where PaymentBundle does not ship the hook
    public function testEveryDeliveringBasketProviderIsRefundable(): void
    {
        $delivering = $this->deliveringBodies();
        if ([] !== $delivering && !interface_exists($this->refundableInterface())) {
            $this->markTestSkipped(sprintf('%s does not exist here', $this->refundableInterface()));
        }

        $found = [];
        foreach (array_keys($delivering) as $class) {
            if (!is_subclass_of($class, $this->refundableInterface())) {
                $found[$class] = 'delivers without a refund hook';
            }
        }

        $this->assertRule(self::REFUNDABLE, $found, sprintf('Each of these providers delivers on payment without implementing %s', $this->refundableInterface()));
    }

    // Identified as "path: expression", the path from templatesDirectory(): see RAW_SAFE_FILTERS for what may come right before raw
    public function testNoRawOutputOfUnsafeContent(): void
    {
        $found = [];

        foreach ($this->sourceFiles($this->templatesDirectory(), '.twig') as $file) {
            $template = (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($file));
            preg_match_all('/\{[{%]-?(.*?)-?[}%]\}/s', $template, $tags);

            foreach ($tags[1] as $expression) {
                if ($this->hasUnsafeRaw($expression)) {
                    $found[substr($file, strlen(rtrim($this->templatesDirectory(), '/')) + 1) . ': ' . trim((string) preg_replace('/\s+/', ' ', $expression))] = 'raw output';
                }
            }
        }

        $this->assertRule(self::RAW, $found, 'Each of these prints raw what no sanitize_html, json_encode or trans of a key made safe');
    }

    // A bulk DQL or SQL UPDATE/DELETE bypasses the listeners, so the method invalidates by hand (invalidateTags(), invalidate(), a cache delete())
    public function testEveryBulkQueryInvalidatesTheCache(): void
    {
        $found = [];

        foreach ($this->sourceFiles($this->sourceDirectory()) as $file) {
            foreach ($this->methodBodies($this->tokens($file)) as $method => $body) {
                $bulk = preg_match('/->(?:delete|update)\(\s*(?:\)|[\w\\\\]+::class)|[\'"](?:UPDATE|DELETE FROM) /', $body);
                if ($bulk && !preg_match('/invalidat|cache->delete\(|->deleteItems?\(/i', $body)) {
                    $found[$this->className($file) . '::' . $method] = 'bulk query without cache invalidation';
                }
            }
        }

        $this->assertRule(self::BULK_QUERY, $found, 'Each of these methods runs a bulk UPDATE/DELETE without invalidating the cache');
    }

    // A misspelled rule would never be checked, its exceptions staying forever
    public function testEveryAllowedExceptionNamesARule(): void
    {
        $rules = [self::STRING_LENGTH, self::BOUNDED_NUMBER, self::PRE_FLUSH, self::FORM_VALUE, self::EMPTY_DATA, self::BATCH_DELETE, self::TEST_MODE, self::REFUNDABLE, self::RAW, self::BULK_QUERY];
        $unknown = array_values(array_diff(array_keys($this->allowed()), $rules));

        $this->assertSame([], $unknown, sprintf('allowed() names unknown rules: %s', implode(', ', $unknown)));
    }

    // One assertion per rule, naming every problem at once: an unlisted finding, an exception without a reason, an exception matching nothing
    private function assertRule(string $rule, array $found, string $message): void
    {
        $allowed = $this->allowed()[$rule] ?? [];
        $problems = [];

        foreach ($found as $id => $detail) {
            if (!\array_key_exists($id, $allowed)) {
                $problems[] = $id . ' (' . $detail . ')';
            }
        }

        foreach ($allowed as $id => $reason) {
            if ('' === trim((string) $reason)) {
                $problems[] = $id . ' (allowed without a reason)';
            }

            if (!\array_key_exists($id, $found)) {
                $problems[] = $id . ' (allowed but matches nothing any more: remove it from allowed())';
            }
        }

        $this->assertSame([], $problems, sprintf("[%s] %s:\n- %s", $rule, $message, implode("\n- ", $problems)));
    }

    // Every file below a directory ending with the suffix, none when the directory does not exist - this very class excluded, its patterns matching themselves
    /**
     * @return list<string>
     */
    private function sourceFiles(string $directory, string $suffix = '.php'): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = (string) $file;
            if (str_ends_with($path, $suffix) && realpath($path) !== realpath(__FILE__)) {
                $files[] = $path;
            }
        }
        sort($files);

        return $files;
    }

    // The class a file holds, named after its path below sourceDirectory()
    private function className(string $file): string
    {
        $relative = substr($file, strlen(rtrim($this->sourceDirectory(), '/')) + 1, -4);

        return rtrim($this->sourceNamespace(), '\\') . '\\' . str_replace('/', '\\', $relative);
    }

    // The ORM properties each entity, mapped superclass or trait declares itself, keyed "Class::property" - a property a class takes from a trait is checked once, on the trait
    /**
     * @return array<string, \ReflectionProperty>
     */
    private function entityProperties(): array
    {
        $properties = [];

        foreach ($this->sourceFiles($this->sourceDirectory()) as $file) {
            $class = $this->className($file);
            if (!str_contains((string) file_get_contents($file), 'ORM\Column') || (!class_exists($class) && !trait_exists($class))) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() === $class && !$this->comesFromTrait($reflection, $property->getName())) {
                    $properties[$class . '::' . $property->getName()] = $property;
                }
            }
        }

        return $properties;
    }

    // Whether one of the traits the class uses declares the property
    private function comesFromTrait(\ReflectionClass $reflection, string $property): bool
    {
        return array_any($reflection->getTraits(), static fn (\ReflectionClass $trait): bool => $trait->hasProperty($property));
    }

    // The ORM column a property maps, null for a relation or an unmapped property
    private function column(\ReflectionProperty $property): ?Column
    {
        $attributes = $property->getAttributes(Column::class);

        return [] === $attributes ? null : $attributes[0]->newInstance();
    }

    // The declared type of a property, null for a union or none
    private function phpType(\ReflectionProperty $property): ?string
    {
        $type = $property->getType();

        return $type instanceof \ReflectionNamedType ? $type->getName() : null;
    }

    // The validation attributes of a property, those nested in a Sequentially or a Compound included
    /**
     * @return list<Constraint>
     */
    private function constraints(\ReflectionProperty $property): array
    {
        $constraints = [];

        foreach ($property->getAttributes(Constraint::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $constraint = $attribute->newInstance();
            $constraints[] = $constraint;
            if (property_exists($constraint, 'constraints') && \is_array($constraint->constraints)) {
                $constraints = [...$constraints, ...$constraint->constraints];
            }
        }

        return $constraints;
    }

    // The max of the property's Assert\Length, null when it has none
    private function lengthMax(\ReflectionProperty $property): ?int
    {
        foreach ($this->constraints($property) as $constraint) {
            if ($constraint instanceof Length && null !== $constraint->max) {
                return $constraint->max;
            }
        }

        return null;
    }

    // Whether the property is named like an amount (see boundedPropertyNames())
    private function isBoundedName(string $name): bool
    {
        return array_any($this->boundedPropertyNames(), static fn (string $ending): bool => str_ends_with(strtolower($name), strtolower($ending)));
    }

    // The form types below src/Form, each with its code
    /**
     * @return array<string, string> file => code without comments
     */
    private function formFiles(): array
    {
        $files = [];
        foreach ($this->sourceFiles(rtrim($this->sourceDirectory(), '/') . '/Form') as $file) {
            $files[$file] = $this->code($file);
        }

        return $files;
    }

    // Fields added with a literal name - one named by a variable can't be followed statically - the text types only when asked
    /**
     * @return array<string, string> field => its options, as written in ->add()
     */
    private function addedFields(string $file, bool $textOnly = false): array
    {
        $tokens = $this->tokens($file);
        $fields = [];

        foreach ($tokens as $index => $token) {
            if ('add' !== $token[1] || '->' !== ($tokens[$index - 1][1] ?? null) || '(' !== ($tokens[$index + 1][1] ?? null)) {
                continue;
            }

            $arguments = $this->arguments($tokens, $index + 1);
            if (!preg_match('/^[\'"](\w+)[\'"]$/', $arguments[0] ?? '', $name)) {
                continue;
            }

            $type = $arguments[1] ?? '';
            if (!$textOnly || \in_array(preg_replace('/^.*\\\\|::class$/', '', $type), self::TEXT_TYPES, true)) {
                $fields[$name[1]] = $arguments[2] ?? '';
            }
        }

        return $fields;
    }

    // Whether the options leave the field mapped
    private function isMapped(string $options): bool
    {
        return !preg_match('/\'mapped\'\s*=>\s*false/', $options);
    }

    // The data_class a type declares, resolved through the use statements of its source
    private function dataClass(string $code): ?string
    {
        if (!preg_match('/\'data_class\'\s*=>\s*([\w\\\\]+)::class/', $code, $match)) {
            return null;
        }

        $short = ltrim($match[1], '\\');
        if (preg_match('/^use\s+([\w\\\\]+\\\\' . preg_quote($short, '/') . ');/m', $code, $use)) {
            return $use[1];
        }

        preg_match('/^namespace\s+([^;]+);/m', $code, $namespace);

        return str_contains($short, '\\') || !isset($namespace[1]) ? $short : $namespace[1] . '\\' . $short;
    }

    // Whether the field's setter declares a parameter type that refuses null
    private function refusesNull(string $dataClass, string $field): bool
    {
        $setter = 'set' . ucfirst($field);
        if (!class_exists($dataClass) || !method_exists($dataClass, $setter)) {
            return false;
        }

        $type = (new \ReflectionMethod($dataClass, $setter)->getParameters()[0] ?? null)?->getType();

        return null !== $type && !$type->allowsNull();
    }

    // Whether the CRUD controller writes its own version of an EasyAdmin action
    private function overrides(\ReflectionClass $reflection, string $method): bool
    {
        return $reflection->hasMethod($method) && AbstractCrudController::class !== $reflection->getMethod($method)->getDeclaringClass()->getName();
    }

    // The providers whose onBasketPaid() does something, an empty one delivering nothing
    /**
     * @return array<string, string> provider class => body of its onBasketPaid()
     */
    private function deliveringBodies(): array
    {
        $bodies = [];

        foreach ($this->sourceFiles($this->sourceDirectory(), 'BasketItemProvider.php') as $file) {
            $body = $this->methodBodies($this->tokens($file))['onBasketPaid'] ?? null;
            if (null !== $body && '' !== trim($body, ' {};')) {
                $bodies[$this->className($file)] = $body;
            }
        }

        return $bodies;
    }

    // Whether one of the "raw" filters of an expression follows anything but a safe filter, trans counting only on a quoted key
    private function hasUnsafeRaw(string $expression): bool
    {
        preg_match_all('/\|\s*raw\b/', $expression, $raws, \PREG_OFFSET_CAPTURE);

        foreach ($raws[0] as [, $offset]) {
            $before = substr($expression, 0, $offset);
            if (!preg_match('/\|\s*(\w+)\s*(\((?:[^()]++|(?2))*\))?\s*$/', $before, $filter, \PREG_OFFSET_CAPTURE) || !\in_array($filter[1][0], self::RAW_SAFE_FILTERS, true)) {
                return true;
            }

            if ('trans' === $filter[1][0] && !preg_match('/([\'"])[\w.\-]+\1\s*\)?\s*$/', substr($before, 0, $filter[0][1]))) {
                return true;
            }
        }

        return false;
    }

    // A file's tokens without comments, each one [token id or null, text], whitespace kept as one space: strings and comments can't fool the brace matching
    /**
     * @return list<array{0: int|null, 1: string}>
     */
    private function tokens(string $file): array
    {
        $tokens = [];

        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            $token = \is_array($token) ? [$token[0], \T_WHITESPACE === $token[0] ? ' ' : $token[1]] : [null, $token];
            if (!\in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    // A file's code without its comments, which may well mention what a rule looks for
    private function code(string $file): string
    {
        return implode('', array_column($this->tokens($file), 1));
    }

    // The body of every named method, an abstract or interface one having none
    /**
     * @return array<string, string> method name => body text
     */
    private function methodBodies(array $tokens): array
    {
        $bodies = [];

        foreach ($tokens as $index => $token) {
            $name = \T_WHITESPACE === ($tokens[$index + 1][0] ?? null) ? $index + 2 : $index + 1;
            if (\T_FUNCTION !== $token[0] || \T_STRING !== ($tokens[$name][0] ?? null)) {
                continue;
            }

            $open = $name + 1;
            while (isset($tokens[$open]) && !\in_array($tokens[$open][1], ['{', ';'], true)) {
                ++$open;
            }

            if ('{' === ($tokens[$open][1] ?? null)) {
                $bodies[$tokens[$name][1]] = implode('', array_column(\array_slice($tokens, $open, $this->closing($tokens, $open) - $open + 1), 1));
            }
        }

        return $bodies;
    }

    // The index of the token closing the bracket opened at $open
    private function closing(array $tokens, int $open): int
    {
        $depth = 0;
        $count = \count($tokens);

        for ($index = $open; $index < $count; ++$index) {
            $text = $tokens[$index][1];
            $depth += \in_array($text, ['(', '[', '{', '${'], true) ? 1 : 0;
            $depth -= \in_array($text, [')', ']', '}'], true) ? 1 : 0;
            if (0 === $depth) {
                return $index;
            }
        }

        return $count - 1;
    }

    // The arguments of the call whose "(" sits at $open, each as its text
    /**
     * @return list<string>
     */
    private function arguments(array $tokens, int $open): array
    {
        $end = $this->closing($tokens, $open);
        $arguments = [''];
        $depth = 0;

        for ($index = $open + 1; $index < $end; ++$index) {
            $text = $tokens[$index][1];
            $depth += \in_array($text, ['(', '[', '{'], true) ? 1 : 0;
            $depth -= \in_array($text, [')', ']', '}'], true) ? 1 : 0;
            if (0 === $depth && ',' === $text) {
                $arguments[] = '';

                continue;
            }

            $arguments[\count($arguments) - 1] .= $text;
        }

        return array_map(trim(...), $arguments);
    }
}
