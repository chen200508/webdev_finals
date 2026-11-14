<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();

            $form
                ->add('role', ChoiceType::class, [
                    'label' => 'Role',
                    'required' => false,
                    'placeholder' => 'All Roles',
                    'choices' => [
                        'Administrator' => 'ROLE_ADMIN',
                        'Specialist / Staff' => 'ROLE_STAFF',
                        'Customer' => 'ROLE_CUSTOMER',
                    ],
                ])
                ->add('status', ChoiceType::class, [
                    'label' => 'Status',
                    'required' => false,
                    'placeholder' => 'All Statuses',
                    'choices' => [
                        'Active' => '1',
                        'Deactivated' => '0',
                    ],
                ])
                ->add('filter', SubmitType::class, [
                    'label' => 'Filter',
                ]);
                
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            foreach ($data as $key => $value) {
                if (\is_string($value)) {
                    $data[$key] = trim($value);
                }
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

