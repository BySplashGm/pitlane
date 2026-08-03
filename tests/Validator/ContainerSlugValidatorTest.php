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

use App\Dto\ServerFormData;
use App\Entity\Server;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Repository\ServerRepositoryInterface;
use App\Validator\ContainerSlug;
use App\Validator\ContainerSlugValidator;
use Override;
use ReflectionProperty;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ContainerSlugValidator>
 */
final class ContainerSlugValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * The slug the stubbed repository reports as already taken; null means every slug is free.
     */
    private ?string $existingSlug = null;

    /**
     * The id carried by the server the stubbed repository returns for {@see $existingSlug}.
     */
    private int $existingServerId = 1;

    public function test_a_null_value_raises_no_violation(): void
    {
        $this->validator->validate(null, new ContainerSlug());

        $this->assertNoViolation();
    }

    public function test_an_empty_value_raises_no_violation(): void
    {
        $this->validator->validate('', new ContainerSlug());

        $this->assertNoViolation();
    }

    public function test_a_name_that_slugifies_to_empty_is_rejected(): void
    {
        // The repository would report even the empty slug as taken, so only the early return keeps
        // this from also raising the "taken" violation.
        $this->existingSlug = '';

        $this->validator->validate('!!!', new ContainerSlug());

        $this->buildViolation('This name must contain at least one letter or number.')->assertRaised();
    }

    public function test_a_reserved_name_is_rejected_regardless_of_casing(): void
    {
        // Same guard: a reserved slug that is also "taken" must stop at the reserved violation.
        $this->existingSlug = 'postgres';

        $this->validator->validate('Postgres', new ContainerSlug());

        $this->buildViolation('This name is reserved by the platform and cannot be used.')->assertRaised();
    }

    public function test_a_taken_slug_is_rejected(): void
    {
        $this->existingSlug = 'monza-cup';

        $this->validator->validate('Monza Cup', new ContainerSlug());

        $this->buildViolation('A server with a similar name already exists.')->assertRaised();
    }

    public function test_an_available_name_raises_no_violation(): void
    {
        $this->validator->validate('Monza Cup', new ContainerSlug());

        $this->assertNoViolation();
    }

    public function test_editing_does_not_clash_with_the_servers_own_row(): void
    {
        $this->existingSlug = 'monza-cup';
        $this->existingServerId = 7;

        $serverFormData = new ServerFormData();
        $serverFormData->serverId = 7;
        $this->setObject($serverFormData);

        $this->validator->validate('Monza Cup', new ContainerSlug());

        $this->assertNoViolation();
    }

    public function test_editing_still_rejects_another_servers_slug(): void
    {
        $this->existingSlug = 'monza-cup';
        $this->existingServerId = 9;

        $serverFormData = new ServerFormData();
        $serverFormData->serverId = 7;
        $this->setObject($serverFormData);

        $this->validator->validate('Monza Cup', new ContainerSlug());

        $this->buildViolation('A server with a similar name already exists.')->assertRaised();
    }

    public function test_it_rejects_a_foreign_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('Monza Cup', new NotBlank());
    }

    public function test_it_rejects_a_non_string_value(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(42, new ContainerSlug());
    }

    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        $serverRepository = self::createStub(ServerRepositoryInterface::class);
        $serverRepository->method('findBySlug')->willReturnCallback(
            fn (string $slug): ?Server => $slug === $this->existingSlug ? $this->makeServer($this->existingServerId) : null,
        );

        return new ContainerSlugValidator($serverRepository);
    }

    private function makeServer(int $id): Server
    {
        $server = new Server(
            name: 'Monza Cup',
            serverName: 'Pitlane Monza',
            track: 'monza',
            trackLayout: null,
            cars: ['ferrari_488'],
            password: '',
            adminPassword: 'admin-secret',
            maxClients: 12,
            tcpPort: 9600,
            udpPort: 9601,
            httpPort: 9602,
            sessionType: SessionType::Race,
            sessionDuration: 15,
            durationUnit: DurationUnit::Minutes,
            weatherGraphics: '3_clear',
            ambientTemp: 20,
            trackTemp: 26,
            dynamicTrack: false,
            trackGrip: 100,
            tcpNoDelay: true,
            registerToLobby: true,
        );

        new ReflectionProperty(Server::class, 'id')->setValue($server, $id);

        return $server;
    }
}
