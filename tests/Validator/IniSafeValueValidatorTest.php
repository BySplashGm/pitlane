<?php

declare(strict_types=1);

/*
 * This file is part of Pitlane.
 *
 * (c) Maxime Valin
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Validator;

use App\Validator\IniSafeValue;
use App\Validator\IniSafeValueValidator;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<IniSafeValueValidator>
 */
final class IniSafeValueValidatorTest extends ConstraintValidatorTestCase
{
    private const string MESSAGE = 'This value must not contain line breaks, "[", "]", "/", "\\", "..", or leading or trailing whitespace.';

    public function test_a_null_value_raises_no_violation(): void
    {
        $this->validator->validate(null, new IniSafeValue());

        $this->assertNoViolation();
    }

    public function test_an_empty_string_raises_no_violation(): void
    {
        $this->validator->validate('', new IniSafeValue());

        $this->assertNoViolation();
    }

    /**
     * @param non-empty-string $value
     */
    #[DataProvider('cleanValues')]
    public function test_a_clean_value_raises_no_violation(string $value): void
    {
        $this->validator->validate($value, new IniSafeValue());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cleanValues(): iterable
    {
        yield 'plain name' => ['Pitlane Monza'];
        yield 'punctuated password' => ['p@ssw0rd!#-_'];
        yield 'single dots kept apart' => ['round.1.final'];
    }

    /**
     * @param non-empty-string $value
     */
    #[DataProvider('unsafeValues')]
    public function test_an_unsafe_value_is_rejected(string $value): void
    {
        $this->validator->validate($value, new IniSafeValue());

        $this->buildViolation(self::MESSAGE)->assertRaised();
    }

    /**
     * Each row isolates a single forbidden trait so removing the matching rule stops raising the
     * violation for that row alone.
     *
     * @return iterable<string, array{string}>
     */
    public static function unsafeValues(): iterable
    {
        yield 'newline' => ["name\nADMIN_PASSWORD=x"];
        yield 'carriage return' => ["name\rADMIN_PASSWORD=x"];
        yield 'open bracket' => ['name[SECTION'];
        yield 'close bracket' => ['SECTION]name'];
        yield 'forward slash' => ['dir/name'];
        yield 'backslash' => ['dir\\name'];
        yield 'parent directory token' => ['a..b'];
        yield 'leading whitespace' => [' name'];
        yield 'trailing whitespace' => ['name '];
    }

    public function test_it_rejects_a_foreign_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('name', new NotBlank());
    }

    public function test_it_rejects_a_non_string_value(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(42, new IniSafeValue());
    }

    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new IniSafeValueValidator();
    }
}
