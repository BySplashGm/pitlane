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
        $user = new User('admin@pitlane.test', UserRole::Admin);
        $user->setPassword('old-hash');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->doctrineUserRepository->upgradePassword($user, 'new-hash');
        $this->entityManager->clear();

        $reloaded = $this->doctrineUserRepository->find($user->getId());
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
