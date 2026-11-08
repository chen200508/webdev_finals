<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class ServiceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $form = $event->getForm();
            $data = $event->getData() ?? [];

            $categoryChoices = [];
            foreach ($options['categories'] as $category) {
                $id = (int) ($category['id'] ?? 0);
                $name = (string) ($category['name'] ?? '');
                if ($id > 0 && $name !== '') {
                    $categoryChoices[$name] = $id;
                }
            }

            if (\is_array($data) && !isset($data['duration'])) {
                $data['duration'] = 60;
                $event->setData($data);
            }

            $form
                ->add('service_name', TextType::class, [
                    'label' => 'Service name',
                    'required' => true,
                    'attr' => [
                        'placeholder' => 'Service name',
                    ],
                    'constraints' => [
                        new NotBlank(message: 'Please enter a service name.'),
                    ],
                ])
                ->add('category_id', ChoiceType::class, [
                    'label' => 'Category',
                    'required' => true,
                    'choices' => $categoryChoices,
                ])
                ->add('duration', IntegerType::class, [
                    'label' => 'Duration',
                    'required' => true,
                    'attr' => [
                        'min' => 15,
                    ],
                    'constraints' => [
                        new GreaterThanOrEqual(value: 15, message: 'Duration must be at least 15 minutes.'),
                    ],
                ])
                ->add('price', NumberType::class, [
                    'label' => 'Price',
                    'required' => true,
                    'scale' => 2,
                    'html5' => true,
                    'attr' => [
                        'min' => 0,
                        'step' => '0.01',
                        'placeholder' => 'Price',
                    ],
                    'constraints' => [
                        new NotBlank(message: 'Please enter a price.'),
                        new GreaterThanOrEqual(value: 0, message: 'Price cannot be negative.'),
                    ],
                ])
                ->add('description', TextType::class, [
                    'label' => 'Description',
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Description (optional)',
                    ],
                ])
                ->add('add', SubmitType::class, [
                    'label' => 'Add',
                ]);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            if (isset($data['service_name'])) {
                $data['service_name'] = trim((string) $data['service_name']);
            }

            if (isset($data['description'])) {
                $data['description'] = trim((string) $data['description']);
            }

            if (empty($data['duration'])) {
                $data['duration'] = '60';
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'POST',
            'categories' => [],
        ]);

        $resolver->setAllowedTypes('categories', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

