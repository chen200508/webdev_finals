<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AppointmentFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $form = $event->getForm();

            $serviceChoices = [];
            foreach ($options['services'] as $service) {
                $name = (string) ($service['name'] ?? '');
                if ($name !== '') {
                    $serviceChoices[$name] = $name;
                }
            }

            $staffChoices = [];
            foreach ($options['staff'] as $member) {
                $id = (int) ($member['id'] ?? 0);
                $fullName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
                if ($id > 0 && $fullName !== '') {
                    $staffChoices[$fullName] = (string) $id;
                }
            }

            $form
                ->add('service', ChoiceType::class, [
                    'label' => 'Service',
                    'required' => false,
                    'placeholder' => 'All Services',
                    'choices' => $serviceChoices,
                ])
                ->add('staff_id', ChoiceType::class, [
                    'label' => 'Artist',
                    'required' => false,
                    'placeholder' => 'All Artists',
                    'choices' => $staffChoices,
                ])
                ->add('status_id', ChoiceType::class, [
                    'label' => 'Status',
                    'required' => false,
                    'placeholder' => 'All Statuses',
                    'choices' => [
                        'Pending' => '1',
                        'Confirmed' => '2',
                        'Completed' => '3',
                        'Cancelled' => '4',
                    ],
                ])
                ->add('appointment_date', DateType::class, [
                    'label' => 'Date',
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'string',
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
            'services' => [],
            'staff' => [],
        ]);

        $resolver->setAllowedTypes('services', 'array');
        $resolver->setAllowedTypes('staff', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

