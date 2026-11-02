<?php

namespace Omnibridge\Tests\Validation;

use Omnibridge\Validation\AttributeConstraints;
use Omnibridge\Validation\Constraint\Count;
use Omnibridge\Validation\Constraint\Email;
use Omnibridge\Validation\Constraint\Length;
use Omnibridge\Validation\Constraint\Unique;
use Omnibridge\Validation\Validator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A rule written once: a validator's own attribute read as a kept constraint, by its name, its class
 * never loaded, by the plain validator as by omnibridge's own attributes.
 */
final class AttributeConstraintsTest extends TestCase
{
    public function testARegisteredReaderDoesNotHideTheKnownOnes(): void
    {
        $readers = new \ReflectionClass(AttributeConstraints::class);
        $readers->setStaticPropertyValue('readers', []);
        $readers->setStaticPropertyValue('defaults', false);
        AttributeConstraints::register(HostUnique::class, static fn (): ?Unique => null);

        self::assertCount(2, AttributeConstraints::ofProperty(new \ReflectionProperty(Rules::class, 'email')));
    }

    public function testAValidatorsAttributeIsReadWithoutItsClass(): void
    {
        $constraints = AttributeConstraints::ofProperty(new \ReflectionProperty(Rules::class, 'email'));

        self::assertCount(2, $constraints);
        self::assertInstanceOf(Email::class, $constraints[0]);
        self::assertSame(['new', 'edit'], $constraints[0]->groups);
        self::assertInstanceOf(Length::class, $constraints[1]);
        self::assertSame(20, $constraints[1]->max);
    }

    public function testAnAttributeNoReaderKnowsIsLeftAlone(): void
    {
        self::assertSame([], AttributeConstraints::ofProperty(new \ReflectionProperty(Rules::class, 'password')));
    }

    public function testThePlainValidatorAnswersThem(): void
    {
        $rules = new Rules();
        $rules->destinations = [];
        $violations = (new Validator())->validate($rules);

        self::assertSame(['destinations'], array_map(static fn ($violation) => $violation->path, $violations));
        self::assertSame('newsletter.destinations.required', $violations[0]->message);
        self::assertSame(Count::TOO_FEW, $violations[0]->code);
    }

    public function testAClassLevelRegisteredReader(): void
    {
        AttributeConstraints::register(HostUnique::class, static fn (array $arguments): Unique => AttributeConstraints::unique($arguments, 'taken'));

        [$constraint] = AttributeConstraints::ofClass(Rules::class);
        self::assertInstanceOf(Unique::class, $constraint);
        self::assertSame(['email'], $constraint->fields);
        self::assertSame('taken', $constraint->message);
    }

    public function testUniqueOnTheValuesAFormHolds(): void
    {
        $asked = [];
        $validator = new Validator(static function (string $class, array $criteria) use (&$asked): bool {
            $asked = [$class, $criteria];

            return true;
        });
        $violations = $validator->only(['email' => 'a@b.c', 'other' => 'x'], [new Unique(['email'], Rules::class)]);

        self::assertSame([Rules::class, ['email' => 'a@b.c']], $asked);
        self::assertSame('email', $violations[0]->path);
    }
}

#[\Attribute(\Attribute::TARGET_CLASS)]
final class HostUnique
{
}

#[HostUnique(fields: ['email'])]
final class Rules
{
    #[Assert\Email(groups: ['new', 'edit'])]
    #[Assert\Length(max: 20, groups: ['new'])]
    public ?string $email = null;

    #[Assert\NotCompromisedPassword]
    public ?string $password = null;

    #[Assert\Count(min: 1, minMessage: 'newsletter.destinations.required')]
    public array $destinations = [];
}
