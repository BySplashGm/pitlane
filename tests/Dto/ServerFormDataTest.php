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

namespace App\Tests\Dto;

use App\Dto\ServerFormData;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Repository\ServerRepository;
use App\Validator\ContainerSlug;
use App\Validator\ContainerSlugValidator;
use App\Validator\IniSafeValue;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ServerFormDataTest extends TestCase
{
    public function test_it_exposes_deterministic_defaults(): void
    {
        $serverFormData = new ServerFormData();

        self::assertNull($serverFormData->serverId);
        self::assertSame('', $serverFormData->name);
        self::assertSame('', $serverFormData->serverName);
        self::assertSame('', $serverFormData->track);
        self::assertNull($serverFormData->trackLayout);
        self::assertSame([], $serverFormData->cars);
        self::assertNull($serverFormData->password);
        self::assertSame('', $serverFormData->adminPassword);
        self::assertSame(12, $serverFormData->maxClients);
        self::assertSame(0, $serverFormData->tcpPort);
        self::assertSame(0, $serverFormData->udpPort);
        self::assertSame(0, $serverFormData->httpPort);
        self::assertSame(SessionType::Race, $serverFormData->sessionType);
        self::assertSame(15, $serverFormData->sessionDuration);
        self::assertSame(DurationUnit::Minutes, $serverFormData->durationUnit);
        self::assertSame('', $serverFormData->weatherGraphics);
        self::assertSame(20, $serverFormData->ambientTemp);
        self::assertSame(26, $serverFormData->trackTemp);
        self::assertFalse($serverFormData->dynamicTrack);
        self::assertSame(100, $serverFormData->trackGrip);
        self::assertTrue($serverFormData->tcpNoDelay);
        self::assertTrue($serverFormData->registerToLobby);
    }

    public function test_to_server_maps_every_field(): void
    {
        $serverFormData = new ServerFormData();
        $serverFormData->name = 'Monza Cup';
        $serverFormData->serverName = 'Pitlane Monza';
        $serverFormData->track = 'monza';
        $serverFormData->trackLayout = 'monza_junior';
        $serverFormData->cars = ['ferrari_488', 'porsche_911'];
        $serverFormData->password = 'join-secret';
        $serverFormData->adminPassword = 'admin-secret';
        $serverFormData->maxClients = 18;
        $serverFormData->tcpPort = 9600;
        $serverFormData->udpPort = 9601;
        $serverFormData->httpPort = 9602;
        $serverFormData->sessionType = SessionType::Qualify;
        $serverFormData->sessionDuration = 30;
        $serverFormData->durationUnit = DurationUnit::Laps;
        $serverFormData->weatherGraphics = '3_clear';
        $serverFormData->ambientTemp = 24;
        $serverFormData->trackTemp = 30;
        $serverFormData->dynamicTrack = true;
        $serverFormData->trackGrip = 95;
        $serverFormData->tcpNoDelay = true;
        $serverFormData->registerToLobby = true;

        $server = $serverFormData->toServer();

        self::assertSame('Monza Cup', $server->getName());
        self::assertSame('Pitlane Monza', $server->getServerName());
        self::assertSame('monza', $server->getTrack());
        self::assertSame('monza_junior', $server->getTrackLayout());
        self::assertSame(['ferrari_488', 'porsche_911'], $server->getCars());
        self::assertSame('join-secret', $server->getPassword());
        self::assertSame('admin-secret', $server->getAdminPassword());
        self::assertSame(18, $server->getMaxClients());
        self::assertSame(9600, $server->getTcpPort());
        self::assertSame(9601, $server->getUdpPort());
        self::assertSame(9602, $server->getHttpPort());
        self::assertSame(SessionType::Qualify, $server->getSessionType());
        self::assertSame(30, $server->getSessionDuration());
        self::assertSame(DurationUnit::Laps, $server->getDurationUnit());
        self::assertSame('3_clear', $server->getWeatherGraphics());
        self::assertSame(24, $server->getAmbientTemp());
        self::assertSame(30, $server->getTrackTemp());
        self::assertTrue($server->isDynamicTrack());
        self::assertSame(95, $server->getTrackGrip());
        self::assertTrue($server->isTcpNoDelay());
        self::assertTrue($server->isRegisterToLobby());
    }

    public function test_to_server_defaults_a_blank_join_password_to_empty_string(): void
    {
        $serverFormData = new ServerFormData();
        $serverFormData->cars = ['ferrari_488'];

        self::assertSame('', $serverFormData->toServer()->getPassword());
    }

    public function test_the_name_field_carries_the_container_slug_constraint(): void
    {
        $attributes = new ReflectionProperty(ServerFormData::class, 'name')->getAttributes(ContainerSlug::class);

        self::assertCount(1, $attributes);
    }

    /**
     * @param non-empty-string $field
     */
    #[DataProvider('iniSafeFields')]
    public function test_free_text_ini_fields_carry_the_ini_safe_value_constraint(string $field): void
    {
        $attributes = new ReflectionProperty(ServerFormData::class, $field)->getAttributes(IniSafeValue::class);

        self::assertCount(1, $attributes);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function iniSafeFields(): iterable
    {
        yield 'serverName' => ['serverName'];
        yield 'password' => ['password'];
        yield 'adminPassword' => ['adminPassword'];
    }

    public function test_valid_ports_raise_no_port_violation(): void
    {
        $violations = $this->violations($this->validFormData());

        self::assertNotContains('tcpPort: This port is reserved by the platform and cannot be used.', $violations);
        self::assertNotContains('udpPort: This port is reserved by the platform and cannot be used.', $violations);
        self::assertNotContains('httpPort: This port is reserved by the platform and cannot be used.', $violations);
        self::assertNotContains('httpPort: The HTTP port must differ from the TCP port.', $violations);
    }

    public function test_a_reserved_tcp_port_is_rejected(): void
    {
        $serverFormData = $this->validFormData();
        $serverFormData->tcpPort = 8080;

        self::assertContains('tcpPort: This port is reserved by the platform and cannot be used.', $this->violations($serverFormData));
    }

    public function test_a_reserved_udp_port_is_rejected(): void
    {
        $serverFormData = $this->validFormData();
        $serverFormData->udpPort = 5432;

        self::assertContains('udpPort: This port is reserved by the platform and cannot be used.', $this->violations($serverFormData));
    }

    public function test_a_reserved_http_port_is_rejected(): void
    {
        $serverFormData = $this->validFormData();
        $serverFormData->httpPort = 8000;

        self::assertContains('httpPort: This port is reserved by the platform and cannot be used.', $this->violations($serverFormData));
    }

    public function test_an_http_port_equal_to_the_tcp_port_is_rejected(): void
    {
        $serverFormData = $this->validFormData();
        $serverFormData->tcpPort = 9600;
        $serverFormData->httpPort = 9600;

        self::assertContains('httpPort: The HTTP port must differ from the TCP port.', $this->violations($serverFormData));
    }

    public function test_valid_admin_password_raises_no_admin_password_violation(): void
    {
        $violations = $this->violations($this->validFormData());

        self::assertNotContains('adminPassword: The admin password must be at least 8 characters long.', $violations);
        self::assertNotContains('adminPassword: The admin password must differ from the join password.', $violations);
    }

    public function test_a_short_admin_password_is_rejected(): void
    {
        $serverFormData = $this->validFormData();
        $serverFormData->adminPassword = 'shortpw';

        self::assertContains('adminPassword: The admin password must be at least 8 characters long.', $this->violations($serverFormData));
    }

    public function test_an_eight_character_admin_password_satisfies_the_length(): void
    {
        $serverFormData = $this->validFormData();
        $serverFormData->adminPassword = 'length08';

        self::assertNotContains('adminPassword: The admin password must be at least 8 characters long.', $this->violations($serverFormData));
    }

    public function test_an_admin_password_equal_to_the_join_password_is_rejected(): void
    {
        $serverFormData = $this->validFormData();
        $serverFormData->password = 'join-secret';
        $serverFormData->adminPassword = 'join-secret';

        self::assertContains('adminPassword: The admin password must differ from the join password.', $this->violations($serverFormData));
    }

    public function test_a_distinct_join_password_leaves_the_admin_password_valid(): void
    {
        $serverFormData = $this->validFormData();
        $serverFormData->password = 'join-secret';

        self::assertNotContains('adminPassword: The admin password must differ from the join password.', $this->violations($serverFormData));
    }

    public function test_a_blank_join_password_is_not_compared_to_the_admin_password(): void
    {
        $serverFormData = $this->validFormData();
        $serverFormData->password = '';
        $serverFormData->adminPassword = 'admin-secret';

        self::assertNotContains('adminPassword: The admin password must differ from the join password.', $this->violations($serverFormData));
    }

    private function validFormData(): ServerFormData
    {
        $serverFormData = new ServerFormData();
        $serverFormData->name = 'Monza Cup';
        $serverFormData->serverName = 'Pitlane Monza';
        $serverFormData->track = 'monza';
        $serverFormData->cars = ['ferrari_488'];
        $serverFormData->adminPassword = 'admin-secret';
        $serverFormData->weatherGraphics = '3_clear';
        $serverFormData->tcpPort = 9600;
        $serverFormData->udpPort = 9601;
        $serverFormData->httpPort = 9602;

        return $serverFormData;
    }

    /**
     * @return list<string> every violation as "propertyPath: message"
     */
    private function violations(ServerFormData $serverFormData): array
    {
        $messages = [];
        foreach ($this->validator()->validate($serverFormData) as $constraintViolationList) {
            $messages[] = \sprintf('%s: %s', $constraintViolationList->getPropertyPath(), $constraintViolationList->getMessage());
        }

        return $messages;
    }

    /**
     * The container-slug constraint needs an injected repository, so the plain validator builder is
     * given a factory that supplies {@see ContainerSlugValidator} with a stub (every slug free) and
     * defers every other constraint to the default factory.
     */
    private function validator(): ValidatorInterface
    {
        $serverRepository = self::createStub(ServerRepository::class);

        $constraintValidatorFactory = new class($serverRepository) extends ConstraintValidatorFactory {
            public function __construct(
                private readonly ServerRepository $serverRepository,
            ) {
                parent::__construct();
            }

            #[Override]
            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                return $constraint instanceof ContainerSlug
                    ? new ContainerSlugValidator($this->serverRepository)
                    : parent::getInstance($constraint);
            }
        };

        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory($constraintValidatorFactory)
            ->getValidator();
    }
}
