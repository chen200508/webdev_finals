<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class AppointmentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $form = $event->getForm();
            $data = $event->getData() ?? [];

            $customerChoices = [];
            foreach ($options['customers'] as $customer) {
                $id = (int) ($customer['id'] ?? 0);
                $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
                $email = (string) ($customer['email'] ?? '');
                if ($id > 0 && $name !== '') {
                    $label = $name . ($email !== '' ? ' (' . $email . ')' : '');
                    $customerChoices[$label] = $id;
                }
            }

            $serviceChoices = [];
            foreach ($options['services'] as $service) {
                $id = (int) ($service['id'] ?? 0);
                $name = (string) ($service['name'] ?? '');
                $price = (float) ($service['price'] ?? 0);
                if ($id > 0 && $name !== '') {
                    $label = $name . ($price > 0 ? ' (₱' . number_format($price, 2) . ')' : '');
                    $serviceChoices[$label] = $id;
                }
            }

            $staffChoices = [];
            foreach ($options['staff'] as $member) {
                $id = (int) ($member['id'] ?? 0);
                $fullName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
                if ($id > 0 && $fullName !== '') {
                    $staffChoices[$fullName] = $id;
                }
            }

            if (\is_array($data)) {
                if (!isset($data['appointment_date'])) {
                    $data['appointment_date'] = date('Y-m-d');
                }
                if (!isset($data['start_time'])) {
                    $data['start_time'] = '10:00';
                }
                if (!isset($data['status_id'])) {
                    $data['status_id'] = 1; // Pending
                }
                $event->setData($data);
            }

            $form
                ->add('customer_id', ChoiceType::class, [
                    'label' => 'Select Client',
                    'required' => false,
                    'placeholder' => '— Choose Existing Client —',
                    'choices' => $customerChoices,
                ])
                ->add('new_customer_name', TextType::class, [
                    'label' => 'Client Name (if new)',
                    'required' => false,
                    'attr' => ['placeholder' => 'First and Last name'],
                ])
                ->add('new_customer_email', TextType::class, [
                    'label' => 'Client Email (if new)',
                    'required' => false,
                    'attr' => ['placeholder' => 'client@example.com'],
                ])
                ->add('new_customer_phone', TextType::class, [
                    'label' => 'Client Phone (if new)',
                    'required' => false,
                    'attr' => ['placeholder' => '+63 9xx xxx xxxx'],
                ])
                ->add('service_id', ChoiceType::class, [
                    'label' => 'Service',
                    'required' => true,
                    'choices' => $serviceChoices,
                    'constraints' => [
                        new NotBlank(message: 'Please choose a salon service.'),
                    ],
                ])
                ->add('staff_id', ChoiceType::class, [
                    'label' => 'Artist / Stylist',
                    'required' => true,
                    'choices' => $staffChoices,
                    'constraints' => [
                        new NotBlank(message: 'Please assign an artist or stylist.'),
                    ],
                ])
                ->add('appointment_date', DateType::class, [
                    'label' => 'Date',
                    'required' => true,
                    'widget' => 'single_text',
                    'input' => 'string',
                    'constraints' => [
                        new NotBlank(message: 'Please select an appointment date.'),
                    ],
                ])
                ->add('start_time', TextType::class, [
                    'label' => 'Start Time',
                    'required' => true,
                    'attr' => ['type' => 'time'],
                    'constraints' => [
                        new NotBlank(message: 'Please select a start time.'),
                    ],
                ])
                ->add('price', NumberType::class, [
                    'label' => 'Price (₱)',
                    'required' => true,
                    'scale' => 2,
                    'html5' => true,
                    'attr' => [
                        'min' => 0,
                        'step' => '0.01',
                        'placeholder' => '0.00',
                    ],
                    'constraints' => [
                        new NotBlank(message: 'Please enter a price.'),
                        new GreaterThanOrEqual(value: 0, message: 'Price cannot be negative.'),
                    ],
                ])
                ->add('status_id', ChoiceType::class, [
                    'label' => 'Status',
                    'required' => true,
                    'choices' => [
                        'Pending' => 1,
                        'Confirmed' => 2,
                        'Completed' => 3,
                        'Cancelled' => 4,
                    ],
                ])
                ->add('customer_notes', TextareaType::class, [
                    'label' => 'Notes (Optional)',
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Any customer requests, styling notes, or requirements...',
                        'rows' => 3,
                    ],
                ])
                ->add('submit', SubmitType::class, [
                    'label' => 'Schedule Appointment',
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
            'method' => 'POST',
            'customers' => [],
            'services' => [],
            'staff' => [],
        ]);

        $resolver->setAllowedTypes('customers', 'array');
        $resolver->setAllowedTypes('services', 'array');
        $resolver->setAllowedTypes('staff', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

