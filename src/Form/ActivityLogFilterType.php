<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityLogFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $form = $event->getForm();

            $actorChoices = [];

            // Add registered users dynamically
            $users = (array) ($options['users'] ?? []);
            foreach ($users as $u) {
                $role = (string) ($u['role'] ?? 'ROLE_CUSTOMER');
                $roleLabel = $role === 'ROLE_ADMIN' ? 'Admin' : ($role === 'ROLE_STAFF' ? 'Staff' : 'Customer');
                $name = trim((string) ($u['name'] ?? ''));
                $email = trim((string) ($u['email'] ?? ''));

                if ($name !== '' && $name !== $email) {
                    $label = sprintf('%s (%s) · %s', $name, $email, $roleLabel);
                } else {
                    $label = sprintf('%s · %s', $email, $roleLabel);
                }
                $actorChoices[$label] = $email;
            }

            // Also include any actor present in logs not yet in choices
            foreach ($options['logs'] as $log) {
                $actor = (string) ($log['performed_by'] ?? '');
                if ($actor !== '' && !in_array($actor, $actorChoices, true)) {
                    $performerName = (string) ($log['performer_name'] ?? '');
                    if ($performerName !== '' && $performerName !== $actor) {
                        $label = sprintf('%s (%s)', $performerName, $actor);
                    } else {
                        $label = $actor;
                    }
                    $actorChoices[$label] = $actor;
                }
            }

            $form
                ->add('event_type', ChoiceType::class, [
                    'label' => 'Event Type',
                    'required' => false,
                    'placeholder' => 'All Events',
                    'choices' => [
                        'Booking Created' => 'created',
                        'Status Updated' => 'status_updated',
                        'Booking Updated' => 'appointment_updated',
                        'Session Completed' => 'completed',
                        'Booking Cancelled' => 'cancelled',
                        'Service Created' => 'service_created',
                        'Catalog Updated' => 'service_updated',
                        'Service Removed' => 'service_deleted',
                        'User Created' => 'user_created',
                        'User Updated' => 'user_updated',
                        'User Removed' => 'user_deleted',
                        'Customer Created' => 'customer_created',
                        'Customer Updated' => 'customer_updated',
                        'Customer Removed' => 'customer_deleted',
                        'User Login' => 'user_login',
                        'User Logout' => 'user_logout',
                    ],
                ])
                ->add('performed_by', ChoiceType::class, [
                    'label' => 'Performed By',
                    'required' => false,
                    'placeholder' => 'All Users',
                    'choices' => $actorChoices,
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
            'logs' => [],
            'users' => [],
        ]);

        $resolver->setAllowedTypes('logs', 'array');
        $resolver->setAllowedTypes('users', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

