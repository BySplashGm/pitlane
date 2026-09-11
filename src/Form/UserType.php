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

namespace App\Form;

use App\Dto\UserFormData;
use App\Entity\Server;
use App\Enum\UserRole;
use Override;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<UserFormData>
 */
final class UserType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Labels and help text live in the Twig template, not here: keeping them out of the form leaves
        // nothing cosmetic for mutation testing to pick at. Only behavioural options remain.
        // Required on create; optional on edit, where it doubles as an admin-driven password reset
        // (left blank, the existing password is kept — see UserFormData::validatePassword()).
        $builder
            ->add('email', EmailType::class)
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => false,
                'options' => ['empty_data' => ''],
                'invalid_message' => 'The password fields must match.',
                'first_options' => ['label' => false],
                'second_options' => ['label' => false],
            ])
            ->add('role', EnumType::class, [
                'class' => UserRole::class,
                // Never Owner: it is created exclusively by the pitlane:create-owner console command.
                'choices' => [UserRole::Admin, UserRole::Operator],
            ]);

        // The assignment picker only exists on edit, per the edit-page-only requirement. It is rendered
        // by hand in the template as a search-and-add widget (see _form.html.twig), so it stays
        // collapsed rather than expanded into one checkbox per server.
        if (null !== $options['user_id']) {
            $builder->add('assignedServers', EntityType::class, [
                'class' => Server::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
            ]);
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['data_class' => UserFormData::class])
            ->setRequired('user_id')
            ->setAllowedTypes('user_id', ['int', 'null']);
    }
}
