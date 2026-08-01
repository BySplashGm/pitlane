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

namespace App\Story;

use App\Entity\Server;
use App\Entity\User;
use App\Enum\UserRole;
use App\Factory\ServerFactory;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    private const int SERVER_COUNT = 6;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Override]
    public function build(): void
    {
        $servers = ServerFactory::createSequence(
            array_map(
                static fn (int $index): array => [
                    'name' => \sprintf('Race Server %d', $index),
                    'tcpPort' => 9600 + $index,
                    'udpPort' => 9700 + $index,
                    'httpPort' => 8081 + $index,
                ],
                range(1, self::SERVER_COUNT),
            ),
        );

        UserFactory::createOne(['email' => 'admin@pitlane.local', 'userRole' => UserRole::Admin]);

        $user = UserFactory::createOne(['email' => 'owner@pitlane.local', 'userRole' => UserRole::Owner]);
        $this->assign($user, $servers);

        $firstOperator = UserFactory::createOne(['email' => 'operator1@pitlane.local']);
        $this->assign($firstOperator, \array_slice($servers, 0, 3));

        $secondOperator = UserFactory::createOne(['email' => 'operator2@pitlane.local']);
        $this->assign($secondOperator, \array_slice($servers, 3));

        $this->entityManager->flush();
    }

    /**
     * @param list<Server> $servers
     */
    private function assign(User $user, array $servers): void
    {
        foreach ($servers as $server) {
            $user->assignServer($server);
        }
    }
}
