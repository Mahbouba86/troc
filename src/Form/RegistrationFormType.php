<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;


class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Nom d’utilisateur',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un nom d’utilisateur']),
                    new Length(['min' => 3, 'minMessage' => 'Le nom doit faire au moins {{ limit }} caractères']),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer une adresse email']),
                ],
            ])
            ->add('phoneNumber', TextType::class, [
                'label' => 'Numéro de téléphone',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un numéro de téléphone']),
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer une adresse']),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J’accepte les conditions générales',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(['message' => 'Vous devez accepter les conditions.']),
                ],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'constraints' => [
                    new NotBlank(['message' => 'La ville est obligatoire.']),
                ],
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal',
                'constraints' => [
                    new NotBlank(['message' => 'Le code postal est obligatoire.']),
                    new Regex([
                        'pattern' => '/^\d{4}$/',
                        'message' => 'Le code postal doit contenir exactement 4 chiffres.',
                    ]),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un mot de passe']),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit faire au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
