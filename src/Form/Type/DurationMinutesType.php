<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Compound form type that maps a total-minutes integer to three d/h/min inputs.
 *
 * @implements DataTransformerInterface<int, array{days: int, hours: int, minutes: int}>
 */
final class DurationMinutesType extends AbstractType implements DataTransformerInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('days', IntegerType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => ['min' => 0, 'placeholder' => '0'],
            ])
            ->add('hours', IntegerType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => ['min' => 0, 'max' => 23, 'placeholder' => '0'],
            ])
            ->add('minutes', IntegerType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => ['min' => 0, 'max' => 59, 'placeholder' => '0'],
            ]);

        $builder->addModelTransformer($this);
    }

    /** int minutes → ['days', 'hours', 'minutes'] */
    public function transform(mixed $value): mixed
    {
        $total = is_int($value) ? $value : 0;

        $days    = intdiv($total, 1440);
        $rem     = $total % 1440;
        $hours   = intdiv($rem, 60);
        $minutes = $rem % 60;

        return ['days' => $days, 'hours' => $hours, 'minutes' => $minutes];
    }

    /** ['days', 'hours', 'minutes'] → int minutes */
    public function reverseTransform(mixed $value): mixed
    {
        if (!is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }

        $days    = (int) ($value['days']    ?? 0);
        $hours   = (int) ($value['hours']   ?? 0);
        $minutes = (int) ($value['minutes'] ?? 0);

        if ($hours > 23) {
            throw new TransformationFailedException('Hours out of range.', 0, null, 'Hours must be between 0 and 23.');
        }
        if ($minutes > 59) {
            throw new TransformationFailedException('Minutes out of range.', 0, null, 'Minutes must be between 0 and 59.');
        }

        $total = $days * 1440 + $hours * 60 + $minutes;

        if ($total < 1) {
            throw new TransformationFailedException(
                'Total interval must be at least 1 minute.',
                0,
                null,
                'The interval must be at least 1 minute.'
            );
        }

        return $total;
    }
}
