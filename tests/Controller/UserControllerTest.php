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

use App\Entity\Server;
use App\Entity\User;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Enum\UserRole;
use App\Form\UserType;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserControllerTest extends WebTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;

    private KernelBrowser $kernelBrowser;

    #[Override]
    protected function setUp(): void
    {
        $this->kernelBrowser = self::createClient();
        // Each test reads a form then posts it: keep one kernel so the stateless CSRF cookie stays
        // consistent across both requests.
        $this->kernelBrowser->disableReboot();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->truncateUsers($this->entityManager);
        $this->truncateServers($this->entityManager);
    }

    public function test_owner_can_list_users(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));
        $this->persistUser('operator@pitlane.test', UserRole::Operator);

        $this->kernelBrowser->request('GET', '/users');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'owner@pitlane.test');
        self::assertSelectorTextContains('body', 'operator@pitlane.test');
    }

    public function test_admin_can_list_users(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('admin@pitlane.test', UserRole::Admin));

        $this->kernelBrowser->request('GET', '/users');

        self::assertResponseIsSuccessful();
    }

    public function test_operator_cannot_list_users(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('operator@pitlane.test', UserRole::Operator));

        $this->kernelBrowser->request('GET', '/users');

        self::assertResponseStatusCodeSame(403);
    }

    public function test_operator_cannot_access_the_new_user_page(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('operator@pitlane.test', UserRole::Operator));

        $this->kernelBrowser->request('GET', '/users/new');

        self::assertResponseStatusCodeSame(403);
    }

    public function test_operator_cannot_access_the_edit_user_page(): void
    {
        $user = $this->persistUser('admin@pitlane.test', UserRole::Admin);
        $this->kernelBrowser->loginUser($this->persistUser('operator@pitlane.test', UserRole::Operator));

        $this->kernelBrowser->request('GET', \sprintf('/users/%d/edit', (int) $user->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function test_operator_cannot_delete_a_user(): void
    {
        $user = $this->persistUser('admin@pitlane.test', UserRole::Admin);
        $this->kernelBrowser->loginUser($this->persistUser('operator@pitlane.test', UserRole::Operator));

        $this->kernelBrowser->request('POST', \sprintf('/users/%d/delete', (int) $user->getId()), ['_csrf_token' => 'irrelevant']);

        self::assertResponseStatusCodeSame(403);
    }

    public function test_the_new_form_has_a_password_field_but_no_server_checklist(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $crawler = $this->kernelBrowser->request('GET', '/users/new');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="user[plainPassword][first]"]'));
        self::assertCount(1, $crawler->filter('input[name="user[plainPassword][second]"]'));
        self::assertCount(0, $crawler->filter('input[name^="user[assignedServers]"]'));
    }

    public function test_owner_can_create_an_admin(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $this->submitNewForm(['email' => 'new-admin@pitlane.test', 'plainPassword' => ['first' => 'Str0ng!Passw0rd', 'second' => 'Str0ng!Passw0rd'], 'role' => UserRole::Admin->value]);

        self::assertResponseRedirects('/users');
        $this->kernelBrowser->followRedirect();
        self::assertSelectorTextContains('body', 'User "new-admin@pitlane.test" created.');

        $this->entityManager->clear();
        $created = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'new-admin@pitlane.test']);
        self::assertInstanceOf(User::class, $created);
        self::assertSame(UserRole::Admin, $created->getRole());

        $userPasswordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($userPasswordHasher->isPasswordValid($created, 'Str0ng!Passw0rd'));
    }

    public function test_admin_can_create_an_operator(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('admin@pitlane.test', UserRole::Admin));

        $this->submitNewForm(['email' => 'new-operator@pitlane.test', 'plainPassword' => ['first' => 'Str0ng!Passw0rd', 'second' => 'Str0ng!Passw0rd'], 'role' => UserRole::Operator->value]);

        self::assertResponseRedirects('/users');

        $this->entityManager->clear();
        $created = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'new-operator@pitlane.test']);
        self::assertInstanceOf(User::class, $created);
        self::assertSame(UserRole::Operator, $created->getRole());
    }

    public function test_admin_cannot_create_an_admin(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('admin@pitlane.test', UserRole::Admin));

        $this->submitNewForm(['email' => 'blocked-admin@pitlane.test', 'plainPassword' => ['first' => 'Str0ng!Passw0rd', 'second' => 'Str0ng!Passw0rd'], 'role' => UserRole::Admin->value]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Only the owner can promote a user to admin.');
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'blocked-admin@pitlane.test']));
    }

    public function test_a_blank_password_is_rejected_on_create(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $this->submitNewForm(['email' => 'no-password@pitlane.test', 'plainPassword' => ['first' => '', 'second' => ''], 'role' => UserRole::Operator->value]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'no-password@pitlane.test']));
    }

    public function test_creating_an_owner_is_rejected(): void
    {
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        // A hand-crafted POST bypassing the dropdown: Owner is never an offered choice.
        $this->submitNewForm(['email' => 'second-owner@pitlane.test', 'plainPassword' => ['first' => 'Str0ng!Passw0rd', 'second' => 'Str0ng!Passw0rd'], 'role' => UserRole::Owner->value]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'second-owner@pitlane.test']));
    }

    public function test_the_edit_form_prefills_email_and_role_and_shows_the_server_checklist(): void
    {
        $server = $this->persistServer('Assignable Ring');
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $user->assignServer($server);

        $this->entityManager->flush();
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $crawler = $this->kernelBrowser->request('GET', \sprintf('/users/%d/edit', (int) $user->getId()));

        self::assertResponseIsSuccessful();
        self::assertSame('operator@pitlane.test', $crawler->filter('input[name="user[email]"]')->attr('value'));
        self::assertCount(1, $crawler->filter('input[name="user[plainPassword][first]"]'));
        self::assertCount(1, $crawler->filter('input[name="user[plainPassword][second]"]'));
        self::assertCount(1, $crawler->filter(\sprintf('input[name="user[assignedServers][]"][value="%d"]', (int) $server->getId())));
    }

    public function test_the_edit_form_lists_available_servers_with_id_and_name(): void
    {
        $server = $this->persistServer('Available Ring');
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $crawler = $this->kernelBrowser->request('GET', \sprintf('/users/%d/edit', (int) $user->getId()));

        self::assertResponseIsSuccessful();
        $availableServersJson = (string) $crawler->filter('[data-assigned-servers]')->attr('data-assigned-servers-options');
        self::assertSame(
            [['id' => $server->getId(), 'name' => $server->getName()]],
            json_decode($availableServersJson, true),
        );
    }

    public function test_a_valid_edit_updates_email_role_and_assigned_servers(): void
    {
        $server = $this->persistServer('Newly Assigned');
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $this->submitEditForm($id, [
            'email' => 'renamed-operator@pitlane.test',
            'role' => UserRole::Operator->value,
            'assignedServers' => [(string) $server->getId()],
        ]);

        self::assertResponseRedirects('/users');
        $this->kernelBrowser->followRedirect();
        self::assertSelectorTextContains('body', 'User "renamed-operator@pitlane.test" updated.');

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $updated);
        self::assertSame('renamed-operator@pitlane.test', $updated->getEmail());
        self::assertCount(1, $updated->getAssignedServers());
    }

    public function test_an_edit_omitting_assigned_servers_unassigns_them_all(): void
    {
        $server = $this->persistServer('Formerly Assigned');
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $user->assignServer($server);

        $this->entityManager->flush();
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $this->submitEditForm($id, [
            'email' => 'operator@pitlane.test',
            'role' => UserRole::Operator->value,
        ]);

        self::assertResponseRedirects('/users');

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $updated);
        self::assertCount(0, $updated->getAssignedServers());
    }

    public function test_the_edit_forms_assigned_servers_field_is_not_required(): void
    {
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);

        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        $formView = $formFactory->create(UserType::class, null, ['user_id' => (int) $user->getId()])->createView();

        self::assertFalse($formView['assignedServers']->vars['required']);
    }

    public function test_owner_can_reset_a_users_password(): void
    {
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $this->submitEditForm($id, [
            'email' => 'operator@pitlane.test',
            'plainPassword' => ['first' => 'N3w!Str0ngPassw0rd', 'second' => 'N3w!Str0ngPassw0rd'],
            'role' => UserRole::Operator->value,
        ]);

        self::assertResponseRedirects('/users');

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $updated);

        $userPasswordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($userPasswordHasher->isPasswordValid($updated, 'N3w!Str0ngPassw0rd'));
        self::assertFalse($userPasswordHasher->isPasswordValid($updated, 'hashed-password'));
    }

    public function test_mismatched_reset_passwords_are_rejected(): void
    {
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $this->submitEditForm($id, [
            'email' => 'operator@pitlane.test',
            'plainPassword' => ['first' => 'N3w!Str0ngPassw0rd', 'second' => 'D1fferent!Passw0rd'],
            'role' => UserRole::Operator->value,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'The password fields must match.');

        $this->entityManager->clear();
        $unchanged = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $unchanged);
        self::assertSame('hashed-password', $unchanged->getPassword());
    }

    public function test_leaving_the_password_blank_on_edit_keeps_the_existing_password(): void
    {
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $id = (int) $user->getId();
        $originalPassword = $user->getPassword();
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $this->submitEditForm($id, [
            'email' => 'operator@pitlane.test',
            'role' => UserRole::Operator->value,
        ]);

        self::assertResponseRedirects('/users');

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $updated);
        self::assertSame($originalPassword, $updated->getPassword());
    }

    public function test_a_short_password_is_rejected_when_resetting_it_on_edit(): void
    {
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $this->submitEditForm($id, [
            'email' => 'operator@pitlane.test',
            'plainPassword' => ['first' => 'short', 'second' => 'short'],
            'role' => UserRole::Operator->value,
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_admin_cannot_edit_the_owner_account(): void
    {
        $user = $this->persistUser('owner@pitlane.test', UserRole::Owner);
        $this->kernelBrowser->loginUser($this->persistUser('admin@pitlane.test', UserRole::Admin));

        $this->kernelBrowser->request('GET', \sprintf('/users/%d/edit', (int) $user->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function test_admin_cannot_promote_an_operator_to_admin(): void
    {
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($this->persistUser('admin@pitlane.test', UserRole::Admin));

        $this->submitEditForm($id, ['email' => 'operator@pitlane.test', 'role' => UserRole::Admin->value]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Only the owner can promote a user to admin.');

        $this->entityManager->clear();
        $unchanged = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $unchanged);
        self::assertSame(UserRole::Operator, $unchanged->getRole());
    }

    public function test_admin_can_keep_an_existing_admins_role_unchanged(): void
    {
        $user = $this->persistUser('other-admin@pitlane.test', UserRole::Admin);
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($this->persistUser('admin@pitlane.test', UserRole::Admin));

        $this->submitEditForm($id, ['email' => 'renamed-admin@pitlane.test', 'role' => UserRole::Admin->value]);

        self::assertResponseRedirects('/users');

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $updated);
        self::assertSame('renamed-admin@pitlane.test', $updated->getEmail());
        self::assertSame(UserRole::Admin, $updated->getRole());
    }

    public function test_owner_can_promote_an_operator_to_admin(): void
    {
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $this->submitEditForm($id, ['email' => 'operator@pitlane.test', 'role' => UserRole::Admin->value]);

        self::assertResponseRedirects('/users');

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(User::class)->find($id);
        self::assertInstanceOf(User::class, $updated);
        self::assertSame(UserRole::Admin, $updated->getRole());
    }

    public function test_owner_can_delete_a_user(): void
    {
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $this->submitDelete($id);

        self::assertResponseRedirects('/users');
        $this->kernelBrowser->followRedirect();
        self::assertSelectorTextContains('body', 'User "operator@pitlane.test" deleted.');

        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(User::class)->find($id));
    }

    public function test_admin_can_delete_an_operator(): void
    {
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($this->persistUser('admin@pitlane.test', UserRole::Admin));

        $this->submitDelete($id);

        self::assertResponseRedirects('/users');

        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(User::class)->find($id));
    }

    public function test_admin_cannot_delete_the_owner_account(): void
    {
        $user = $this->persistUser('owner@pitlane.test', UserRole::Owner);
        $this->kernelBrowser->loginUser($this->persistUser('admin@pitlane.test', UserRole::Admin));

        $this->kernelBrowser->request('POST', \sprintf('/users/%d/delete', (int) $user->getId()), ['_csrf_token' => 'irrelevant']);

        self::assertResponseStatusCodeSame(403);
    }

    public function test_an_invalid_csrf_token_rejects_the_delete(): void
    {
        $user = $this->persistUser('operator@pitlane.test', UserRole::Operator);
        $id = (int) $user->getId();
        $this->kernelBrowser->loginUser($this->persistUser('owner@pitlane.test', UserRole::Owner));

        $this->kernelBrowser->request('POST', \sprintf('/users/%d/delete', $id), ['_csrf_token' => 'forged-token']);

        self::assertResponseRedirects('/users');
        $this->kernelBrowser->followRedirect();
        self::assertSelectorTextContains('body', 'Invalid CSRF token, please retry.');

        $this->entityManager->clear();
        self::assertInstanceOf(User::class, $this->entityManager->getRepository(User::class)->find($id));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->truncateUsers($this->entityManager);
        $this->truncateServers($this->entityManager);
        parent::tearDown();
    }

    /**
     * @param array<string, string|list<string>|array{first: string, second: string}> $payload
     */
    private function submitNewForm(array $payload): void
    {
        // Read the form first so the client holds the stateless CSRF cookie and its token.
        $crawler = $this->kernelBrowser->request('GET', '/users/new');
        $payload['_token'] = (string) $crawler->filter('input[name="user[_token]"]')->attr('value');

        $this->kernelBrowser->request('POST', '/users/new', ['user' => $payload]);
    }

    /**
     * @param array<string, string|list<string>|array{first: string, second: string}> $payload
     */
    private function submitEditForm(int $id, array $payload): void
    {
        $editPath = \sprintf('/users/%d/edit', $id);
        // Read the form first so the client holds the stateless CSRF cookie and its token.
        $crawler = $this->kernelBrowser->request('GET', $editPath);
        $payload['_token'] = (string) $crawler->filter('input[name="user[_token]"]')->attr('value');

        $this->kernelBrowser->request('POST', $editPath, ['user' => $payload]);
    }

    /**
     * Reads the index page so the client holds the stateless CSRF cookie and the delete form's token,
     * then posts the delete form for the given user's row.
     */
    private function submitDelete(int $id): void
    {
        $actionPath = \sprintf('/users/%d/delete', $id);
        $crawler = $this->kernelBrowser->request('GET', '/users');
        $token = (string) $crawler->filter(\sprintf('form[action="%s"] input[name="_csrf_token"]', $actionPath))->attr('value');

        $this->kernelBrowser->request('POST', $actionPath, ['_csrf_token' => $token]);
    }

    private function persistUser(string $email, UserRole $userRole): User
    {
        $user = new User($email, $userRole);
        $user->setPassword('hashed-password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function persistServer(string $name, int $portOffset = 0): Server
    {
        $server = new Server(
            name: $name,
            serverName: \sprintf('Pitlane - %s', $name),
            track: 'spa',
            trackLayout: null,
            cars: ['ks_ferrari_488_gt3'],
            password: '',
            adminPassword: 'admin-secret',
            maxClients: 20,
            tcpPort: 9600 + $portOffset,
            udpPort: 9700 + $portOffset,
            httpPort: 8081 + $portOffset,
            sessionType: SessionType::Race,
            sessionDuration: 60,
            durationUnit: DurationUnit::Minutes,
            weatherGraphics: '3_clear',
            ambientTemp: 22,
            trackTemp: 28,
            dynamicTrack: true,
            trackGrip: 96,
            tcpNoDelay: true,
            registerToLobby: true,
        );
        $server->generateContainerSlug();

        $this->entityManager->persist($server);
        $this->entityManager->flush();

        return $server;
    }
}
