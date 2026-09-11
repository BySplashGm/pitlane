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

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountControllerTest extends WebTestCase
{
    use ResetsDatabase;

    private const string CURRENT_PASSWORD = 'current-password';

    private EntityManagerInterface $entityManager;

    private KernelBrowser $kernelBrowser;

    private UserPasswordHasherInterface $userPasswordHasher;

    #[Override]
    protected function setUp(): void
    {
        $this->kernelBrowser = self::createClient();
        // Each test reads a form then posts it: keep one kernel so the stateless CSRF cookie stays
        // consistent across both requests.
        $this->kernelBrowser->disableReboot();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->userPasswordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->truncateUsers($this->entityManager);
    }

    public function test_the_account_page_prefills_the_current_email(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('operator@pitlane.test'));

        $crawler = $this->kernelBrowser->request('GET', '/account');

        self::assertResponseIsSuccessful();
        self::assertSame('operator@pitlane.test', $crawler->filter('input[name="account[email]"]')->attr('value'));
    }

    public function test_any_authenticated_role_can_reach_their_own_account_page(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('operator@pitlane.test', UserRole::Operator));

        $this->kernelBrowser->request('GET', '/account');

        self::assertResponseIsSuccessful();
    }

    public function test_a_valid_update_changes_the_email(): void
    {
        $user = $this->persistUser('operator@pitlane.test');
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($user);

        $this->submitAccountForm([
            'email' => 'renamed@pitlane.test',
            'currentPassword' => self::CURRENT_PASSWORD,
        ]);

        self::assertResponseRedirects('/account');
        $this->kernelBrowser->followRedirect();
        self::assertSelectorTextContains('body', 'Account updated.');

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $updated);
        self::assertSame('renamed@pitlane.test', $updated->getEmail());
    }

    public function test_a_valid_update_with_a_new_password_changes_the_password(): void
    {
        $user = $this->persistUser('operator@pitlane.test');
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($user);

        $this->submitAccountForm([
            'email' => 'operator@pitlane.test',
            'currentPassword' => self::CURRENT_PASSWORD,
            'newPassword' => ['first' => 'Brand!New9Passw0rd', 'second' => 'Brand!New9Passw0rd'],
        ]);

        self::assertResponseRedirects('/account');

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $updated);
        self::assertTrue($this->userPasswordHasher->isPasswordValid($updated, 'Brand!New9Passw0rd'));
        self::assertFalse($this->userPasswordHasher->isPasswordValid($updated, self::CURRENT_PASSWORD));
    }

    public function test_leaving_the_new_password_blank_keeps_the_existing_password(): void
    {
        $user = $this->persistUser('operator@pitlane.test');
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($user);

        $this->submitAccountForm([
            'email' => 'operator@pitlane.test',
            'currentPassword' => self::CURRENT_PASSWORD,
        ]);

        self::assertResponseRedirects('/account');

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $updated);
        self::assertTrue($this->userPasswordHasher->isPasswordValid($updated, self::CURRENT_PASSWORD));
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        $user = $this->persistUser('operator@pitlane.test');
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($user);

        $this->submitAccountForm([
            'email' => 'renamed@pitlane.test',
            'currentPassword' => 'not-the-right-password',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Current password is incorrect.');

        $this->entityManager->clear();
        $unchanged = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $unchanged);
        self::assertSame('operator@pitlane.test', $unchanged->getEmail());
    }

    public function test_a_short_new_password_is_rejected(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('operator@pitlane.test'));

        $this->submitAccountForm([
            'email' => 'operator@pitlane.test',
            'currentPassword' => self::CURRENT_PASSWORD,
            'newPassword' => ['first' => 'short', 'second' => 'short'],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_mismatched_new_passwords_are_rejected(): void
    {
        $user = $this->persistUser('operator@pitlane.test');
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($user);

        $this->submitAccountForm([
            'email' => 'operator@pitlane.test',
            'currentPassword' => self::CURRENT_PASSWORD,
            'newPassword' => ['first' => 'Brand!New9Passw0rd', 'second' => 'Diff3rent!Passw0rd'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'The password fields must match.');

        $this->entityManager->clear();
        $unchanged = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $unchanged);
        self::assertTrue($this->userPasswordHasher->isPasswordValid($unchanged, self::CURRENT_PASSWORD));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->truncateUsers($this->entityManager);
        parent::tearDown();
    }

    private function persistUser(string $email, UserRole $userRole = UserRole::Operator): User
    {
        $user = new User($email, $userRole);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, self::CURRENT_PASSWORD));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param array<string, string|array{first: string, second: string}> $payload
     */
    private function submitAccountForm(array $payload): void
    {
        // Read the form first so the client holds the stateless CSRF cookie and its token.
        $crawler = $this->kernelBrowser->request('GET', '/account');
        $payload['_token'] = (string) $crawler->filter('input[name="account[_token]"]')->attr('value');

        $this->kernelBrowser->request('POST', '/account', ['account' => $payload]);
    }
}
