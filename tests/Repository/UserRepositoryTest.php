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

namespace App\Tests\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserRepositoryTest extends KernelTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;

    private UserRepository $userRepository;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->userRepository = self::getContainer()->get(UserRepository::class);

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

        self::assertFalse($this->userRepository->ownerExists());
    }

    public function test_owner_exists_is_true_once_an_owner_is_persisted(): void
    {
        $user = new User('owner@pitlane.test', UserRole::Owner);
        $user->setPassword('hashed-password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        self::assertTrue($this->userRepository->ownerExists());
    }

    public function test_upgrade_password_persists_the_new_hash(): void
    {
        // The user is never persisted beforehand, so upgradePassword must itself persist it
        // for the new hash to reach the database.
        $user = new User('admin@pitlane.test', UserRole::Admin);
        $user->setPassword('old-hash');

        $this->userRepository->upgradePassword($user, 'new-hash');
        $this->entityManager->clear();

        $reloaded = $this->userRepository->findOneBy(['email' => 'admin@pitlane.test']);
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame('new-hash', $reloaded->getPassword());
    }

    public function test_upgrade_password_rejects_unsupported_users(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $this->userRepository->upgradePassword(new InMemoryUser('system', null), 'new-hash');
    }

    public function test_save_persists_a_new_user(): void
    {
        $user = new User('admin@pitlane.test', UserRole::Admin);
        $user->setPassword('hashed-password');

        $this->userRepository->save($user);
        $this->entityManager->clear();

        $reloaded = $this->userRepository->findOneBy(['email' => 'admin@pitlane.test']);
        self::assertInstanceOf(User::class, $reloaded);
    }

    public function test_remove_deletes_the_user(): void
    {
        $user = new User('admin@pitlane.test', UserRole::Admin);
        $user->setPassword('hashed-password');

        $this->userRepository->save($user);

        $this->userRepository->remove($user);

        $this->entityManager->clear();

        self::assertNull($this->userRepository->findOneBy(['email' => 'admin@pitlane.test']));
    }

    public function test_find_all_ordered_by_email_sorts_ascending(): void
    {
        $second = new User('second@pitlane.test', UserRole::Admin);
        $second->setPassword('hashed-password');

        $first = new User('first@pitlane.test', UserRole::Operator);
        $first->setPassword('hashed-password');

        $this->entityManager->persist($second);
        $this->entityManager->persist($first);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $users = $this->userRepository->findAllOrderedByEmail();

        self::assertSame(['first@pitlane.test', 'second@pitlane.test'], array_map(static fn (User $user): string => $user->getEmail(), $users));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->truncateUsers($this->entityManager);
        parent::tearDown();
    }
}
