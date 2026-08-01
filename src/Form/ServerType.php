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

use App\Dto\ServerFormData;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ServerFormData>
 */
final class ServerType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Labels and help text live in the Twig template, not here: keeping them out of the form
        // leaves nothing cosmetic for mutation testing to pick at. Only behavioural options remain.
        // The cars collection relies on CollectionType's default TextType entry.

        // Identification
        $builder
            ->add('name', TextType::class)
            ->add('serverName', TextType::class);

        // Track
        $builder
            ->add('track', TextType::class)
            ->add('trackLayout', TextType::class, ['required' => false]);

        // Cars
        $builder
            ->add('cars', CollectionType::class, [
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
            ]);

        // Access
        $builder
            ->add('password', TextType::class, ['required' => false])
            ->add('adminPassword', TextType::class)
            ->add('maxClients', IntegerType::class, ['empty_data' => '12']);

        // Ports
        $builder
            ->add('tcpPort', IntegerType::class, ['empty_data' => '0'])
            ->add('udpPort', IntegerType::class, ['empty_data' => '0'])
            ->add('httpPort', IntegerType::class, ['empty_data' => '0']);

        // Session
        $builder
            ->add('sessionType', EnumType::class, ['class' => SessionType::class])
            ->add('sessionDuration', IntegerType::class, ['empty_data' => '15'])
            ->add('durationUnit', EnumType::class, ['class' => DurationUnit::class]);

        // Weather
        $builder
            ->add('weatherGraphics', TextType::class)
            ->add('ambientTemp', IntegerType::class, ['empty_data' => '20'])
            ->add('trackTemp', IntegerType::class, ['empty_data' => '26']);

        // Dynamic track
        $builder
            ->add('dynamicTrack', CheckboxType::class, ['required' => false])
            ->add('trackGrip', IntegerType::class, ['empty_data' => '100']);

        // Options
        $builder
            ->add('tcpNoDelay', CheckboxType::class, ['required' => false])
            ->add('registerToLobby', CheckboxType::class, ['required' => false]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServerFormData::class,
        ]);
    }
}
