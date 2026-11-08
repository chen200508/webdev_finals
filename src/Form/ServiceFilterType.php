<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $form = $event->getForm();

            $categoryChoices = [];
            foreach ($options['categories'] as $category) {
                $name = (string) ($category['name'] ?? '');
                if ($name !== '') {
                    $categoryChoices[$name] = $name;
                }
            }

            $form
                ->add('category', ChoiceType::class, [
                    'label' => 'Category',
                    'required' => false,
                    'placeholder' => 'All Categories',
                    'choices' => $categoryChoices,
                ])
                ->add('duration', ChoiceType::class, [
                    'label' => 'Duration',
                    'required' => false,
                    'placeholder' => 'All Durations',
                    'choices' => [
                        'Up to 45 mins' => '45',
                        '60 mins' => '60',
                        '90 mins' => '90',
                        '120+ mins' => '120',
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
            'categories' => [],
        ]);

        $resolver->setAllowedTypes('categories', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

