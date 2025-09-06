<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ContactMessageReplyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reply', TextareaType::class, [
                'label' => 'Votre réponse',
                'required' => true,
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Écrivez votre réponse ici...',
                ],
            ]);
    }
}
