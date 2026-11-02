<?php

namespace Omnibridge\Tests\Validation;

use Omnibridge\Contract\Violation;
use Omnibridge\Validation\Constraint\Choice;
use Omnibridge\Validation\Constraint\Email;
use Omnibridge\Validation\Constraint\File;
use Omnibridge\Validation\Constraint\Length;
use Omnibridge\Validation\Constraint\NotBlank;
use Omnibridge\Validation\Constraint\Range;
use Omnibridge\Validation\Constraint\Regex;
use Omnibridge\Validation\Constraint\Unique;
use Omnibridge\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    /** @param list<Violation> $violations @return list<string> */
    private static function messages(array $violations): array
    {
        return array_map(static fn (Violation $v): string => ('' === $v->path ? '' : $v->path.': ').$v->message, $violations);
    }

    public function testTheKeptConstraintsWithSymfonysMessages(): void
    {
        $validator = new Validator();

        $this->assertSame(['This value should not be blank.'], self::messages($validator->validate('', [new NotBlank()])));
        $this->assertSame([], self::messages($validator->validate('0', [new NotBlank()])));
        $this->assertSame([], self::messages($validator->validate(null, [new NotBlank(allowNull: true)])));
        $this->assertSame(['This value is too short. It should have 3 characters or more.'], self::messages($validator->validate('ab', [new Length(min: 3)])));
        $this->assertSame(['This value is too long. It should have 1 character or less.'], self::messages($validator->validate('ab', [new Length(max: 1)])));
        $this->assertSame([], self::messages($validator->validate('été', [new Length(max: 3)])));
        $this->assertSame(['This value is not a valid email address.'], self::messages($validator->validate('ringo@', [new Email()])));
        $this->assertSame([], self::messages($validator->validate('ringo@example.com', [new Email()])));
        $this->assertSame(['This value is not valid.'], self::messages($validator->validate('ab1', [new Regex('/^[a-z]+$/')])));
        $this->assertSame(['The value you selected is not a valid choice.'], self::messages($validator->validate('x', [new Choice(['a', 'b'])])));
        $this->assertSame(['You must select at least 1 choice.'], self::messages($validator->validate([], [new Choice(['a', 'b'], multiple: true, min: 1)])));
        $this->assertSame(['This value should be between 1 and 5.'], self::messages($validator->validate(6, [new Range(min: 1, max: 5)])));
        $this->assertSame(['This value should be 1 or more.'], self::messages($validator->validate(0, [new Range(min: 1)])));
        $this->assertSame([], self::messages($validator->validate(null, [new Length(min: 3), new Email(), new Range(max: 1)])));
    }

    public function testAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'omnibridge');
        file_put_contents($path, str_repeat('a', 2000));
        try {
            $this->assertSame(['The file is too large (2 kB). Allowed maximum size is 1 kB.'], self::messages((new Validator())->validate($path, [new File(maxSize: 1000)])));
            $this->assertSame(['The mime type of the file is invalid ("text/plain"). Allowed mime types are "image/*".'], self::messages((new Validator())->validate(new \SplFileInfo($path), [new File(mimeTypes: ['image/*'])])));
            $this->assertSame([], self::messages((new Validator())->validate($path, [new File(maxSize: 3000, mimeTypes: ['text/*'])])));
        } finally {
            unlink($path);
        }
    }

    public function testAnObjectAgainstItsAttributesAndGroups(): void
    {
        $record = new SignUp();
        $record->email = 'nope';

        $this->assertSame([
            'email: This value is not a valid email address.',
            'username: This value should not be blank.',
        ], self::messages((new Validator(static fn (): bool => false))->validate($record)));
        $this->assertSame([
            'email: This value is not a valid email address.',
            'username: This value should not be blank.',
            'password: This value should not be blank.',
        ], self::messages((new Validator(static fn (): bool => false))->validate($record, [], ['Default', 'registration'])));
    }

    public function testUniqueAsksTheLookup(): void
    {
        $record = new SignUp();
        $record->email = 'ringo@example.com';
        $record->username = 'ringo';
        $asked = [];
        $validator = new Validator(static function (string $class, array $criteria, ?object $self) use (&$asked, $record): bool {
            $asked[] = [$class, $criteria, $self === $record];

            return 'ringo' === ($criteria['username'] ?? null);
        });

        $this->assertSame(['username: This value is already used.'], self::messages($validator->validate($record)));
        $this->assertSame([[SignUp::class, ['username' => 'ringo'], true]], $asked);

        $this->expectException(\LogicException::class);
        (new Validator())->validate($record);
    }

    public function testAnUnknownConstraintIsRefusedAloud(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Validator())->validate('a', [new \stdClass()]);
    }

    public function testATranslatorTranslatesTheTemplate(): void
    {
        $validator = new Validator(null, static fn (string $template, array $parameters): string => 'fr:'.strtr(explode('|', $template)[0], $parameters));

        $this->assertSame(['fr:This value should not be blank.'], self::messages($validator->validate(null, [new NotBlank()])));
    }
}

#[Unique(fields: ['username'])]
final class SignUp
{
    #[Email]
    public ?string $email = null;

    #[NotBlank]
    public ?string $username = null;

    #[NotBlank(groups: ['registration'])]
    public ?string $password = null;
}
