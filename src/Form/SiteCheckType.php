<?php

declare(strict_types=1);

namespace App\Form;

use App\Check\CheckRegistry;
use App\Entity\SiteCheck;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SiteCheckType extends AbstractType
{
    public function __construct(
        private readonly CheckRegistry $checkRegistry,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Check type',
                'choices' => $this->checkRegistry->getTypeChoices(),
                'attr' => ['class' => 'form-input'],
            ])
            ->add('checkIntervalMinutes', IntegerType::class, [
                'label' => 'Interval (minutes)',
                'constraints' => [new \Symfony\Component\Validator\Constraints\GreaterThan(0)],
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
            ]);

        // Config as JSON textarea
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $data = $event->getData();
            $form = $event->getForm();

            $configJson = '';
            if ($data instanceof SiteCheck && $data->getConfig() !== []) {
                $configJson = json_encode($data->getConfig(), JSON_PRETTY_PRINT);
            }

            $form->add('configJson', TextareaType::class, [
                'label' => 'Config (JSON)',
                'mapped' => false,
                'required' => false,
                'data' => $configJson,
                'attr' => [
                    'class' => 'form-input font-mono text-sm',
                    'rows' => 5,
                    'placeholder' => '{"container_name": "my-app"}',
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SiteCheck::class]);
    }
}
