<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\SecurityController;
use App\Entity\User;
use App\Enum\UserRole;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityControllerTest extends WebTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;

    private KernelBrowser $kernelBrowser;

    #[Override]
    protected function setUp(): void
    {
        // WebTestCase boots the kernel through createClient(); it must be the only boot,
        // so the client is created here and reused by every test rather than re-created.
        $this->kernelBrowser = self::createClient();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->truncateUsers($this->entityManager);
    }

    public function test_login_page_is_accessible_to_anonymous_users(): void
    {
        $kernelBrowser = $this->kernelBrowser;
        $kernelBrowser->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
    }

    public function test_anonymous_user_is_redirected_to_login_for_protected_routes(): void
    {
        $kernelBrowser = $this->kernelBrowser;
        $kernelBrowser->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function test_invalid_credentials_redirect_back_to_login_with_an_error(): void
    {
        $kernelBrowser = $this->kernelBrowser;
        $this->createUser('operator@pitlane.test', 'correct-password', UserRole::Operator);

        $crawler = $kernelBrowser->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'operator@pitlane.test',
            '_password' => 'wrong-password',
        ]);
        $kernelBrowser->submit($form);

        self::assertResponseRedirects('/login');
        $kernelBrowser->followRedirect();
        self::assertStringContainsString('Invalid credentials', (string) $kernelBrowser->getResponse()->getContent());
    }

    public function test_valid_credentials_authenticate_the_user(): void
    {
        $kernelBrowser = $this->kernelBrowser;
        $this->createUser('owner@pitlane.test', 'correct-password', UserRole::Owner);

        $crawler = $kernelBrowser->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'owner@pitlane.test',
            '_password' => 'correct-password',
        ]);
        $kernelBrowser->submit($form);

        self::assertResponseRedirects('/');

        // Following the redirect proves the session is now fully authenticated:
        // the protected homepage renders instead of bouncing back to /login.
        $kernelBrowser->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function test_logout_clears_the_session_and_redirects_to_login(): void
    {
        $kernelBrowser = $this->kernelBrowser;
        $user = $this->createUser('admin@pitlane.test', 'correct-password', UserRole::Admin);
        $kernelBrowser->loginUser($user);

        // The same-origin CSRF check relies on the Referer header, which a real browser always sends.
        $kernelBrowser->request('GET', '/logout', ['_csrf_token' => 'test'], [], ['HTTP_REFERER' => 'http://localhost/']);

        self::assertResponseRedirects('/login');

        $kernelBrowser->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function test_logout_action_is_never_reached_directly(): void
    {
        // The route is intercepted by the firewall's logout listener before the controller runs;
        // this only verifies the documented Symfony scaffolding behaves as expected if it ever is.
        $this->expectException(LogicException::class);

        new SecurityController()->logout();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->truncateUsers($this->entityManager);
        parent::tearDown();
    }

    private function createUser(string $email, string $plainPassword, UserRole $userRole): User
    {
        $user = new User($email, $userRole);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
