<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'pitlane:create-owner',
    description: 'Create the initial owner account',
)]
final class CreateOwnerCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        if ($this->userRepository->ownerExists()) {
            $symfonyStyle->error('An owner account already exists. Only one owner is allowed.');

            return Command::FAILURE;
        }

        $email = $symfonyStyle->ask(
            'Owner email',
            validator: fn (mixed $answer): string => $this->validateAnswer($answer, [new NotBlank(), new Email()]),
        );
        \assert(\is_string($email));

        $password = $symfonyStyle->askHidden(
            'Owner password',
            fn (mixed $answer): string => $this->validateAnswer($answer, [new Length(min: 8)]),
        );
        \assert(\is_string($password));

        $user = new User($email, UserRole::Owner);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $symfonyStyle->success(\sprintf('Owner account "%s" created.', $email));

        return Command::SUCCESS;
    }

    /**
     * @param list<NotBlank|Email|Length> $constraints
     */
    private function validateAnswer(mixed $answer, array $constraints): string
    {
        $answer = \is_string($answer) ? $answer : '';

        $constraintViolationList = $this->validator->validate($answer, $constraints);
        if (\count($constraintViolationList) > 0) {
            throw new InvalidArgumentException((string) $constraintViolationList->get(0)->getMessage());
        }

        return $answer;
    }
}
