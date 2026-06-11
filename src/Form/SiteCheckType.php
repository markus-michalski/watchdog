<?php

declare(strict_types=1);

namespace App\Form;

use App\Check\CheckRegistry;
use App\Entity\SiteCheck;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<SiteCheck> */
class SiteCheckType extends AbstractType
{
    public function __construct(
        private readonly CheckRegistry $checkRegistry,
    ) {
    }

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
            ->add('runAtTime', TimeType::class, [
                'label' => 'Daily at (optional)',
                'required' => false,
                'input' => 'string',
                'widget' => 'single_text',
                'help' => 'If set, the check runs once daily at this time. The interval above is ignored.',
                'attr' => ['class' => 'form-input w-28'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SiteCheck::class]);
    }
}
