<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;

trait ResetsDatabase
{
    private function truncateUsers(EntityManagerInterface $entityManager): void
    {
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
    }
}
