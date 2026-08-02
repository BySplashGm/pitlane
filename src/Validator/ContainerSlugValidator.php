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

namespace App\Validator;

use App\Dto\ServerFormData;
use App\Entity\Server;
use App\Repository\ServerRepository;
use App\Slug\ContainerSlugger;
use Override;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ContainerSlugValidator extends ConstraintValidator
{
    /**
     * Slugs that would clash with the platform's own containers or reserved words.
     *
     * @var list<string>
     */
    private const array RESERVED_SLUGS = ['postgres', 'pitlane', 'app', 'db'];

    public function __construct(
        private readonly ServerRepository $serverRepository,
    ) {
    }

    /**
     * @throws UnexpectedTypeException  when applied through a constraint other than {@see ContainerSlug}
     * @throws UnexpectedValueException when the validated value is not a string
     */
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ContainerSlug) {
            throw new UnexpectedTypeException($constraint, ContainerSlug::class);
        }

        // An empty name is the NotBlank constraint's job, not this one.
        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $slug = ContainerSlugger::slugify($value);

        if ('' === $slug) {
            $this->context->buildViolation($constraint->emptyMessage)->addViolation();

            return;
        }

        if (\in_array($slug, self::RESERVED_SLUGS, true)) {
            $this->context->buildViolation($constraint->reservedMessage)->addViolation();

            return;
        }

        $existingServer = $this->serverRepository->findBySlug($slug);
        if ($existingServer instanceof Server && $existingServer->getId() !== $this->editedServerId()) {
            $this->context->buildViolation($constraint->takenMessage)->addViolation();
        }
    }

    /**
     * The id of the server currently being edited, so its own persisted row does not count as a
     * clash; null when creating or when validated outside a {@see ServerFormData}.
     */
    private function editedServerId(): ?int
    {
        $object = $this->context->getObject();

        return $object instanceof ServerFormData ? $object->serverId : null;
    }
}
