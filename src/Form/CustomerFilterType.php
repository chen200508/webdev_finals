<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $form = $event->getForm();

            $domainChoices = [];
            foreach ($options['customers'] as $customer) {
                $email = (string) ($customer['email'] ?? '');
                if (str_contains($email, '@')) {
                    $domain = '@' . substr(strrchr($email, '@'), 1);
                    $domainChoices[$domain] = $domain;
                }
            }

            $form
                ->add('keyword', TextType::class, [
                    'label' => 'Customer',
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Name, email, or phone...',
                    ],
                ])
                ->add('email_domain', ChoiceType::class, [
                    'label' => 'Email Domain',
                    'required' => false,
                    'placeholder' => 'All Domains',
                    'choices' => $domainChoices,
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
            'customers' => [],
        ]);

        $resolver->setAllowedTypes('customers', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

