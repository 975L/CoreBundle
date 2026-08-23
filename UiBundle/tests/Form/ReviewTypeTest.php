<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Form\ReviewType;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\ReviewService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

// What a visitor fills to leave a review, and what the form refuses before anything is stored
class ReviewTypeTest extends TestCase
{
    public function testTheFormCarriesTheFourFieldsAReviewIsMadeOf(): void
    {
        $fields = $this->buildFields();

        $this->assertSame(TextType::class, $fields['authorName']['type']);
        $this->assertSame(EmailType::class, $fields['authorEmail']['type']);
        $this->assertSame(ChoiceType::class, $fields['rating']['type']);
        $this->assertSame(TextareaType::class, $fields['comment']['type']);
    }

    // Someone who wants to say something without scoring anything is still leaving a review, and forcing a number would make them invent one
    public function testTheScoreIsOptionalAndOffersToLeaveNone(): void
    {
        $rating = $this->buildFields()['rating'];

        $this->assertFalse($rating['options']['required']);
        $this->assertArrayHasKey('placeholder', $rating['options']);
    }

    // Out of five and not on the site's ui-rating-scale, which belongs to the ratings: a form offering ten would store a score the template shows out of five, and an imported review beside it is on five whatever the site set
    public function testTheScoreIsOfferedOutOfFive(): void
    {
        $this->assertSame([1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5], $this->buildFields()['rating']['options']['choices']);
    }

    // A review with no name, no address or no text is not a review
    public function testTheNameTheAddressAndTheTextAreAllRequired(): void
    {
        $fields = $this->buildFields();

        foreach (['authorName', 'authorEmail', 'comment'] as $name) {
            $this->assertNotBlankIsAmong($fields[$name]['options']['constraints'], $name);
        }
    }

    // An address the site cannot answer is an address that carries no vote either (see ReviewService::voterFor())
    public function testTheAddressIsCheckedAsOne(): void
    {
        $this->assertTrue($this->hasConstraint($this->buildFields()['authorEmail']['options']['constraints'], Email::class));
    }

    // A paste of a whole novel is refused before it reaches the database, the column being a TEXT and the limit therefore ours to set
    public function testTheTextIsBoundedByWhatTheServiceStates(): void
    {
        $length = $this->constraint($this->buildFields()['comment']['options']['constraints'], Length::class);

        $this->assertInstanceOf(Length::class, $length);
        $this->assertSame(ReviewService::MAX_COMMENT_LENGTH, $length->max);
    }

    // The same honeypot every other public form of this bundle carries, rather than one of its own
    public function testTheSharedHoneypotIsAddedAndNotOneOfItsOwn(): void
    {
        $botProtection = $this->createMock(FormBotProtection::class);
        $botProtection->expects($this->once())->method('addHoneypotField');

        $this->buildFields(botProtection: $botProtection);
    }

    // The form fills the entity itself, so what a controller stores is what was submitted and nothing it had to copy over
    public function testTheFormIsBoundToTheReviewEntity(): void
    {
        $resolver = new OptionsResolver();
        $this->type()->configureOptions($resolver);

        $this->assertSame(Review::class, $resolver->resolve()['data_class']);
    }

    /**
     * Records the fields buildForm() adds, keeping each one's type and options - a stubbed builder is enough, the type branching on nothing.
     *
     * @return array<string, array{type: ?string, options: array<string, mixed>}>
     */
    private function buildFields(?FormBotProtection $botProtection = null): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        $this->type($botProtection)->buildForm($builder, []);

        return $added;
    }

    private function type(?FormBotProtection $botProtection = null): ReviewType
    {
        $requestStack = new RequestStack([new Request()]);

        return new ReviewType($botProtection ?? $this->createStub(FormBotProtection::class), $requestStack);
    }

    /**
     * @param object[] $constraints
     */
    private function assertNotBlankIsAmong(array $constraints, string $field): void
    {
        $this->assertTrue($this->hasConstraint($constraints, NotBlank::class), sprintf('"%s" accepts an empty submission', $field));
    }

    /**
     * @param object[] $constraints
     */
    private function hasConstraint(array $constraints, string $class): bool
    {
        return null !== $this->constraint($constraints, $class);
    }

    /**
     * @param object[] $constraints
     */
    private function constraint(array $constraints, string $class): ?object
    {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof $class) {
                return $constraint;
            }
        }

        return null;
    }
}
