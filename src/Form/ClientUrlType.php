<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ClientUrl;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<ClientUrl> */
class ClientUrlType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', UrlType::class, [
                'label' => 'URL',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Url(requireTld: true),
                ],
                'attr' => ['class' => 'form-input', 'placeholder' => 'https://example.com'],
            ])
            ->add('label', TextType::class, [
                'label' => 'Label (optional)',
                'required' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'e.g. Production, Staging'],
                'help' => 'Short name shown in dropdowns. Defaults to the URL if empty.',
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
        $resolver->setDefaults(['data_class' => ClientUrl::class]);
    }
}
