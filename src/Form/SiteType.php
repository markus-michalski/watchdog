<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Site;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['class' => 'form-input'],
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL',
                'constraints' => [new Assert\NotBlank(), new Assert\Url()],
                'attr' => ['class' => 'form-input', 'placeholder' => 'https://example.com'],
            ])
            ->add('checkIntervalMinutes', IntegerType::class, [
                'label' => 'Check interval (minutes)',
                'constraints' => [new Assert\GreaterThan(0)],
                'data' => 5,
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->add('basicAuthUser', TextType::class, [
                'label' => 'BasicAuth username',
                'required' => false,
                'attr' => ['class' => 'form-input', 'autocomplete' => 'off'],
            ])
            ->add('basicAuthPassword', PasswordType::class, [
                'label' => 'BasicAuth password',
                'required' => false,
                'always_empty' => false,
                'attr' => ['class' => 'form-input', 'autocomplete' => 'off'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Site::class]);
    }
}
