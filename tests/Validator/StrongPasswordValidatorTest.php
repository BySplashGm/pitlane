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

use App\Validator\StrongPassword;
use App\Validator\StrongPasswordValidator;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<StrongPasswordValidator>
 */
final class StrongPasswordValidatorTest extends ConstraintValidatorTestCase
{
    public function test_a_null_value_raises_no_violation(): void
    {
        $this->validator->validate(null, new StrongPassword());

        $this->assertNoViolation();
    }

    public function test_an_empty_string_raises_no_violation(): void
    {
        $this->validator->validate('', new StrongPassword());

        $this->assertNoViolation();
    }

    public function test_a_strong_password_raises_no_violation(): void
    {
        $this->validator->validate('Aa1!Aa1!Aa1!', new StrongPassword());

        $this->assertNoViolation();
    }

    public function test_a_maximum_length_password_raises_no_violation(): void
    {
        // Exactly 128 characters, drawing from every class: the upper bound is inclusive.
        $this->validator->validate(str_repeat('Aa1!', 32), new StrongPassword());

        $this->assertNoViolation();
    }

    /**
     * Each row is otherwise-valid and isolates a single failing rule, so removing the matching check
     * stops raising the violation for that row alone.
     *
     * @param non-empty-string $value
     */
    #[DataProvider('singleRuleFailures')]
    public function test_a_password_failing_a_single_rule_is_rejected(string $value, string $message): void
    {
        $this->validator->validate($value, new StrongPassword());

        $this->buildViolation($message)->assertRaised();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function singleRuleFailures(): iterable
    {
        yield 'too short' => ['Aa1!Aa1!Aa1', 'The password must be at least 12 characters long.'];
        yield 'too long' => [str_repeat('Aa1!', 32).'A', 'The password cannot be longer than 128 characters.'];
        yield 'missing uppercase' => ['aa1!aa1!aa1!', 'The password must contain at least one uppercase letter.'];
        yield 'missing lowercase' => ['AA1!AA1!AA1!', 'The password must contain at least one lowercase letter.'];
        yield 'missing digit' => ['Aa!Aa!Aa!Aa!', 'The password must contain at least one digit.'];
    }

    /**
     * Length must be measured in characters, not bytes: a multi-byte character keeps the character
     * count under 12 while pushing the byte count to exactly 12, so a byte-based length check would
     * wrongly accept it.
     */
    public function test_a_password_short_in_characters_but_not_in_bytes_is_rejected(): void
    {
        $this->validator->validate('Aa1!Aa1éAa1', new StrongPassword());

        $this->buildViolation('The password must be at least 12 characters long.')
            ->buildNextViolation('The password can only contain letters, digits and these symbols: {{ symbols }}.')
            ->setParameter('{{ symbols }}', StrongPassword::ALLOWED_SYMBOLS)
            ->assertRaised();
    }

    /**
     * The reverse boundary: exactly 128 characters but a multi-byte character pushes the byte count
     * to 129, so a byte-based length check would wrongly reject it as too long.
     */
    public function test_a_password_at_the_character_limit_but_over_the_byte_limit_is_accepted(): void
    {
        $value = 'Ä'.substr(str_repeat('Aa1!', 32), 1);

        $this->validator->validate($value, new StrongPassword());

        $this->buildViolation('The password can only contain letters, digits and these symbols: {{ symbols }}.')
            ->setParameter('{{ symbols }}', StrongPassword::ALLOWED_SYMBOLS)
            ->assertRaised();
    }

    /**
     * The symbol whitelist is interpolated as a violation parameter rather than baked into the
     * message, so this asserts the raw template plus its substituted value separately.
     */
    public function test_a_password_missing_a_symbol_is_rejected(): void
    {
        $this->validator->validate('Aa1Aa1Aa1Aa1', new StrongPassword());

        $this->buildViolation('The password must contain at least one of these symbols: {{ symbols }}.')
            ->setParameter('{{ symbols }}', StrongPassword::ALLOWED_SYMBOLS)
            ->assertRaised();
    }

    public function test_a_password_with_a_disallowed_character_is_rejected(): void
    {
        $this->validator->validate('Aa1!Aa1;Aa1!', new StrongPassword());

        $this->buildViolation('The password can only contain letters, digits and these symbols: {{ symbols }}.')
            ->setParameter('{{ symbols }}', StrongPassword::ALLOWED_SYMBOLS)
            ->assertRaised();
    }

    public function test_it_rejects_a_foreign_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('Aa1!Aa1!Aa1!', new NotBlank());
    }

    public function test_it_rejects_a_non_string_value(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(42, new StrongPassword());
    }

    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new StrongPasswordValidator();
    }
}
