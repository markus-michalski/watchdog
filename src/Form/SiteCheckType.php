<?php

declare(strict_types=1);

namespace App\Form;

use App\Check\CheckRegistry;
use App\Entity\Agent;
use App\Entity\SiteCheck;
use App\Enum\CheckRunner;
use App\Form\Type\DurationMinutesType;
use App\Repository\AgentRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<SiteCheck> */
class SiteCheckType extends AbstractType
{
    public function __construct(
        private readonly CheckRegistry $checkRegistry,
        private readonly AgentRepository $agentRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Check type',
                'choices' => $this->checkRegistry->getTypeChoices(),
                'attr' => ['class' => 'form-input', 'data-check-type' => ''],
            ])
            ->add('runner', EnumType::class, [
                'label' => 'Runner',
                'class' => CheckRunner::class,
                'choice_label' => fn(CheckRunner $r) => $r->label(),
                'attr' => ['class' => 'form-input', 'data-runner-select' => ''],
            ])
            ->add('agent', EntityType::class, [
                'label' => 'Agent',
                'class' => Agent::class,
                'choices' => $this->agentRepository->findAll(),
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— Select agent —',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('checkIntervalMinutes', DurationMinutesType::class, [
                'label' => 'Interval',
            ])
            ->add('runAtTime', TextType::class, [
                'label' => 'Daily at (optional)',
                'required' => false,
                'help' => 'HH:MM — if set, runs once daily at this time. The interval above is ignored.',
                'attr' => ['class' => 'form-input w-28', 'placeholder' => 'e.g. 08:30'],
            ])
            ->add('retentionDays', IntegerType::class, [
                'label' => 'Keep results for (days)',
                'required' => false,
                'help' => 'Leave empty to keep forever. Example: 1 for http checks every 5 min, 28 for daily checks.',
                'attr' => ['class' => 'form-input w-28', 'min' => 1, 'placeholder' => 'e.g. 30'],
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
