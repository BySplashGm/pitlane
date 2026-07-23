<?php

declare(strict_types=1);

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

    #[Override]
    protected function tearDown(): void
    {
        $this->truncateUsers($this->entityManager);
        parent::tearDown();
    }

    public function test_it_creates_the_owner_account(): void
    {
        $commandTester = $this->commandTester();

        // The first answer to each question is invalid and must be re-asked before the valid one is accepted.
        $commandTester->setInputs(['not-an-email', 'owner@pitlane.test', 'short', 'a-strong-password']);

        $exitCode = $commandTester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Owner account "owner@pitlane.test" created.', $commandTester->getDisplay());

        $userRepository = self::getContainer()->get(UserRepository::class);
        $owner = $userRepository->findOneBy(['email' => 'owner@pitlane.test']);
        self::assertInstanceOf(User::class, $owner);
        self::assertSame(UserRole::Owner, $owner->getRole());

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($owner, 'a-strong-password'));
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

    private function commandTester(): CommandTester
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);

        $application = new Application($kernel);
        $command = $application->find('pitlane:create-owner');

        return new CommandTester($command);
    }
}
