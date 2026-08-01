<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\DoctrineUserRepository;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;

    private DoctrineUserRepository $doctrineUserRepository;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->doctrineUserRepository = self::getContainer()->get(DoctrineUserRepository::class);

        $this->truncateUsers($this->entityManager);
    }

    public function test_owner_exists_is_false_when_no_owner(): void
    {
        // A non-owner user is present so the finder must filter on the owner role, not just
        // return the first row it finds.
        $user = new User('admin@pitlane.test', UserRole::Admin);
        $user->setPassword('hashed-password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        self::assertFalse($this->doctrineUserRepository->ownerExists());
    }

    public function test_owner_exists_is_true_once_an_owner_is_persisted(): void
    {
        $user = new User('owner@pitlane.test', UserRole::Owner);
        $user->setPassword('hashed-password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        self::assertTrue($this->doctrineUserRepository->ownerExists());
    }

    public function test_upgrade_password_persists_the_new_hash(): void
    {
        // The user is never persisted beforehand, so upgradePassword must itself persist it
        // for the new hash to reach the database.
        $user = new User('admin@pitlane.test', UserRole::Admin);
        $user->setPassword('old-hash');

        $this->doctrineUserRepository->upgradePassword($user, 'new-hash');
        $this->entityManager->clear();

        $reloaded = $this->doctrineUserRepository->findOneBy(['email' => 'admin@pitlane.test']);
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame('new-hash', $reloaded->getPassword());
    }

    public function test_upgrade_password_rejects_unsupported_users(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $this->doctrineUserRepository->upgradePassword(new InMemoryUser('system', null), 'new-hash');
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->truncateUsers($this->entityManager);
        parent::tearDown();
    }
}
