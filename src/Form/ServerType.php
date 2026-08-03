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
use App\Service\AcContentServiceInterface;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ServerFormData>
 */
final class ServerType extends AbstractType
{
    public function __construct(
        private readonly AcContentServiceInterface $acContentService,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Labels and help text live in the Twig template, not here: keeping them out of the form
        // leaves nothing cosmetic for mutation testing to pick at. Only behavioural options remain.
        //
        // Required text fields carry empty_data '': without it an empty submit maps to null and the
        // entity's non-null string setters throw a TypeError before validation can report NotBlank.
        // The content selects instead map an empty submit to null (their DTO properties are nullable).

        // Identification
        $builder
            ->add('name', TextType::class, ['empty_data' => ''])
            ->add('serverName', TextType::class, ['empty_data' => '']);

        // Track. The layout choices depend on the chosen track, so trackLayout is (re)built from the
        // track known at each stage: the loaded data when rendering, the submitted value on POST.
        $builder->add('track', ChoiceType::class, [
            'choices' => $this->choiceValues($this->acContentService->tracks()),
            'placeholder' => 'Select a track',
        ]);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $formEvent): void {
            $data = $formEvent->getData();
            $this->addTrackLayoutField($formEvent->getForm(), $data instanceof ServerFormData ? ($data->track ?? '') : '');
        });
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $formEvent): void {
            // A submitted root form always yields an array; the submitted track may be missing or a
            // non-string, in which case there are no layouts to offer.
            /** @var array<string, mixed> $submitted */
            $submitted = $formEvent->getData();
            $track = \is_string($submitted['track'] ?? null) ? $submitted['track'] : '';

            $this->addTrackLayoutField($formEvent->getForm(), $track);
        });

        // Cars: a repeatable list of installed-car values driven by the template's search picker, so
        // the same car can appear more than once. Membership is enforced server-side by the DTO's
        // Assert\Choice, not here. The entry type is left at its default: the rows are rendered by hand
        // in the template as hidden inputs, so it never surfaces.
        $builder->add('cars', CollectionType::class, [
            'allow_add' => true,
            'allow_delete' => true,
        ]);

        // Access
        $builder
            ->add('password', TextType::class, ['required' => false])
            ->add('adminPassword', TextType::class, ['empty_data' => ''])
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
            ->add('weatherGraphics', ChoiceType::class, [
                'choices' => $this->choiceValues($this->acContentService->weather()),
                'placeholder' => 'Select weather',
            ])
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

    /**
     * Adds (or replaces) the trackLayout field with the layouts of the given track as its choices; an
     * empty track, or one with no layouts, leaves an empty, optional select.
     *
     * @param FormInterface<mixed> $form
     */
    private function addTrackLayoutField(FormInterface $form, string $track): void
    {
        // trackLayouts() already returns an empty list for an empty or unknown track, so no guard here.
        $form->add('trackLayout', ChoiceType::class, [
            'required' => false,
            'placeholder' => 'No layout',
            'choices' => $this->choiceValues($this->acContentService->trackLayouts($track)),
        ]);
    }

    /**
     * Maps a list of content folder names to a ChoiceType `choices` array (label => value) that keeps
     * the folder name as the submitted value.
     *
     * @param list<string> $values
     *
     * @return array<string, string>
     */
    private function choiceValues(array $values): array
    {
        return array_combine($values, $values);
    }
}
