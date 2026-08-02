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

namespace App\Tests\Form;

use App\Dto\ServerFormData;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Form\ServerType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Test\TypeTestCase;

#[AllowMockObjectsWithoutExpectations]
final class ServerTypeTest extends TypeTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function validSubmission(): array
    {
        return [
            'name' => 'Monza Cup',
            'serverName' => 'Pitlane Monza',
            'track' => 'monza',
            'trackLayout' => 'monza_junior',
            'cars' => ['ferrari_488', 'porsche_911'],
            'password' => 'join-secret',
            'adminPassword' => 'admin-secret',
            'maxClients' => '18',
            'tcpPort' => '9600',
            'udpPort' => '9601',
            'httpPort' => '9602',
            'sessionType' => SessionType::Qualify->value,
            'sessionDuration' => '30',
            'durationUnit' => DurationUnit::Laps->value,
            'weatherGraphics' => '3_clear',
            'ambientTemp' => '24',
            'trackTemp' => '30',
            'dynamicTrack' => '1',
            'trackGrip' => '95',
            'tcpNoDelay' => '1',
            'registerToLobby' => '1',
        ];
    }

    public function test_submitting_valid_data_maps_to_the_dto(): void
    {
        $form = $this->factory->create(ServerType::class);

        $form->submit($this->validSubmission());

        self::assertTrue($form->isSynchronized());

        $serverFormData = $form->getData();
        self::assertInstanceOf(ServerFormData::class, $serverFormData);
        self::assertSame('Monza Cup', $serverFormData->name);
        self::assertSame('Pitlane Monza', $serverFormData->serverName);
        self::assertSame('monza', $serverFormData->track);
        self::assertSame('monza_junior', $serverFormData->trackLayout);
        self::assertSame(['ferrari_488', 'porsche_911'], $serverFormData->cars);
        self::assertSame('join-secret', $serverFormData->password);
        self::assertSame('admin-secret', $serverFormData->adminPassword);
        self::assertSame(18, $serverFormData->maxClients);
        self::assertSame(9600, $serverFormData->tcpPort);
        self::assertSame(9601, $serverFormData->udpPort);
        self::assertSame(9602, $serverFormData->httpPort);
        self::assertSame(SessionType::Qualify, $serverFormData->sessionType);
        self::assertSame(30, $serverFormData->sessionDuration);
        self::assertSame(DurationUnit::Laps, $serverFormData->durationUnit);
        self::assertSame('3_clear', $serverFormData->weatherGraphics);
        self::assertSame(24, $serverFormData->ambientTemp);
        self::assertSame(30, $serverFormData->trackTemp);
        self::assertTrue($serverFormData->dynamicTrack);
        self::assertSame(95, $serverFormData->trackGrip);
        self::assertTrue($serverFormData->tcpNoDelay);
        self::assertTrue($serverFormData->registerToLobby);
    }

    public function test_unchecked_options_map_to_false(): void
    {
        $submission = $this->validSubmission();
        unset($submission['dynamicTrack'], $submission['tcpNoDelay'], $submission['registerToLobby']);

        $form = $this->factory->create(ServerType::class);
        $form->submit($submission);

        $serverFormData = $form->getData();
        self::assertInstanceOf(ServerFormData::class, $serverFormData);
        self::assertFalse($serverFormData->dynamicTrack);
        self::assertFalse($serverFormData->tcpNoDelay);
        self::assertFalse($serverFormData->registerToLobby);
    }

    public function test_cleared_numeric_fields_fall_back_to_their_defaults(): void
    {
        $submission = $this->validSubmission();
        foreach (['maxClients', 'tcpPort', 'udpPort', 'httpPort', 'sessionDuration', 'ambientTemp', 'trackTemp', 'trackGrip'] as $field) {
            $submission[$field] = '';
        }

        $form = $this->factory->create(ServerType::class);
        $form->submit($submission);

        $serverFormData = $form->getData();
        self::assertInstanceOf(ServerFormData::class, $serverFormData);
        self::assertSame(12, $serverFormData->maxClients);
        self::assertSame(0, $serverFormData->tcpPort);
        self::assertSame(0, $serverFormData->udpPort);
        self::assertSame(0, $serverFormData->httpPort);
        self::assertSame(15, $serverFormData->sessionDuration);
        self::assertSame(20, $serverFormData->ambientTemp);
        self::assertSame(26, $serverFormData->trackTemp);
        self::assertSame(100, $serverFormData->trackGrip);
    }

    public function test_blank_car_rows_are_dropped(): void
    {
        $submission = $this->validSubmission();
        $submission['cars'] = ['ferrari_488', '', 'porsche_911'];

        $form = $this->factory->create(ServerType::class);
        $form->submit($submission);

        $serverFormData = $form->getData();
        self::assertInstanceOf(ServerFormData::class, $serverFormData);
        // Pruning the blank row leaves an index gap; the entity gets the re-indexed list.
        self::assertSame(['ferrari_488', 'porsche_911'], $serverFormData->toServer()->getCars());
    }

    public function test_existing_cars_can_be_removed(): void
    {
        $serverFormData = new ServerFormData();
        $serverFormData->cars = ['ferrari_488', 'porsche_911'];

        $submission = $this->validSubmission();
        $submission['cars'] = ['ferrari_488'];

        $form = $this->factory->create(ServerType::class, $serverFormData);
        $form->submit($submission);

        self::assertSame(['ferrari_488'], $serverFormData->cars);
    }

    public function test_optional_fields_are_not_required(): void
    {
        $formView = $this->factory->create(ServerType::class)->createView();

        self::assertFalse($formView['trackLayout']->vars['required']);
        self::assertFalse($formView['password']->vars['required']);
        self::assertFalse($formView['dynamicTrack']->vars['required']);
        self::assertFalse($formView['tcpNoDelay']->vars['required']);
        self::assertFalse($formView['registerToLobby']->vars['required']);
    }
}
