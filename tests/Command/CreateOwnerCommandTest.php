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

namespace App\Tests\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateOwnerCommandTest extends KernelTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->truncateUsers($this->entityManager);
    }

    public function test_it_creates_the_owner_account(): void
    {
        $commandTester = $this->commandTester();

        // Each question is fed invalid answers first: a blank then a malformed email, and a
        // seven-character (too short) password, all of which must be rejected and re-asked
        // before the trailing valid answer is accepted.
        $commandTester->setInputs(['', 'not-an-email', 'owner@pitlane.test', '1234567', 'passw0rd']);

        $exitCode = $commandTester->execute([]);

        self::assertSame(0, $exitCode);

        $display = $commandTester->getDisplay();
        // The surfaced violation messages prove the first (offset 0) violation is reported.
        self::assertStringContainsString('This value should not be blank.', $display);
        self::assertStringContainsString('This value is not a valid email address.', $display);
        self::assertStringContainsString('This value is too short.', $display);
        self::assertStringContainsString('Owner account "owner@pitlane.test" created.', $display);

        $userRepository = self::getContainer()->get(UserRepository::class);
        $owner = $userRepository->findOneBy(['email' => 'owner@pitlane.test']);
        self::assertInstanceOf(User::class, $owner);
        self::assertSame(UserRole::Owner, $owner->getRole());

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($owner, 'passw0rd'));
    }

    public function test_it_refuses_to_create_a_second_owner(): void
    {
        $user = new User('existing-owner@pitlane.test', UserRole::Owner);
        $user->setPassword('hashed-password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $commandTester = $this->commandTester();
        $exitCode = $commandTester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('An owner account already exists.', $commandTester->getDisplay());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->truncateUsers($this->entityManager);
        parent::tearDown();
    }

    private function commandTester(): CommandTester
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);

        $application = new Application($kernel);
        $command = $application->find('pitlane:create-owner');

        return new CommandTester($command);
    }
}
