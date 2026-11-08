<?php

namespace App\Service;

/**
 * Sample / In-Memory Salon Service for Public Landing & Booking Pages
 * Mirrors the legacy application's categories, treatments, prices, specialists,
 * testimonials, and live slot availability without requiring a database connection.
 */
class SalonService
{
    public function getServiceCategories(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Make up',
                'slug' => 'make-up',
                'description' => 'Unveil your best self with professional makeup design crafted to highlight your natural beauty.',
                'starting_price' => 250,
                'image' => 'images/images_2.webp',
                'items' => [
                    ['name' => 'Daily make up', 'price' => 135],
                    ['name' => 'Night make up', 'price' => 140],
                    ['name' => 'Bridal make up', 'price' => 150],
                    ['name' => 'Ocassion make up', 'price' => 250],
                    ['name' => 'Television make up', 'price' => 170],
                ],
            ],
            [
                'id' => 2,
                'name' => 'Hair styling',
                'slug' => 'hair-styling',
                'description' => 'Transform your look with expert cuts, rich color, and effortless styling designed to complement your lifestyle.',
                'starting_price' => 130,
                'image' => 'images/view.webp',
                'items' => [
                    ['name' => 'Simple haircut', 'price' => 70],
                    ['name' => 'Hair Styling', 'price' => 30],
                    ['name' => 'Full hair color', 'price' => 100],
                    ['name' => 'Protein treatment', 'price' => 150],
                    ['name' => 'Hair mask', 'price' => 180],
                ],
            ],
            [
                'id' => 3,
                'name' => 'Nail care',
                'slug' => 'nail-care',
                'description' => 'Nurture your hands and feet with meticulous care, detailed shaping, and a flawless finish.',
                'starting_price' => 25,
                'image' => 'images/nail.webp',
                'items' => [
                    ['name' => 'Manicure', 'price' => 115],
                    ['name' => 'Pedicure', 'price' => 135],
                    ['name' => 'French Manicure', 'price' => 120],
                ],
            ],
            [
                'id' => 4,
                'name' => 'Cosmetology',
                'slug' => 'cosmetology',
                'description' => 'Rejuvenate your skin with advanced treatments designed to restore balance and enhance tone.',
                'starting_price' => 130,
                'image' => 'images/facw.webp',
                'items' => [
                    ['name' => 'Botox', 'price' => 180],
                    ['name' => 'Chemical Peel', 'price' => 175],
                ],
            ],
            [
                'id' => 5,
                'name' => 'SPA procedures',
                'slug' => 'spa-procedures',
                'description' => 'Escape the stress of daily life with soothing body treatments designed to melt away tension.',
                'starting_price' => 140,
                'image' => 'images/spa.webp',
                'items' => [
                    ['name' => 'Body scrub & smoothing', 'price' => 150],
                    ['name' => 'Indulge & relax', 'price' => 160],
                    ['name' => 'Slimming & drainage', 'price' => 155],
                    ['name' => 'Aromatherapy', 'price' => 180],
                    ['name' => 'Foot revive treatment', 'price' => 199],
                ],
            ],
        ];
    }

    public function getHomeTreatmentPrices(): array
    {
        return [
            ['name' => 'Make up', 'starting_price' => 250],
            ['name' => 'Hair styling', 'starting_price' => 100],
            ['name' => 'Nail care', 'starting_price' => 25],
            ['name' => 'Cosmetology', 'starting_price' => 130],
            ['name' => 'SPA procedures', 'starting_price' => 140],
        ];
    }

    public function getServicesWithPrices(): array
    {
        return [
            ['id' => 1, 'category_id' => 1, 'name' => 'Bridal Makeup & Styling', 'price' => 2500, 'duration_minutes' => 120, 'image' => 'images/Bfacejpg.webp', 'description' => 'Exquisite luxury bridal service including full skin prep, trial planning, lash attachment, and custom airbrushing.'],
            ['id' => 2, 'category_id' => 1, 'name' => 'Everyday Glam', 'price' => 1200, 'duration_minutes' => 60, 'image' => 'images/hair.webp', 'description' => 'Elegant soft makeup contouring with natural lash definition, ideal for social gatherings, lunches, or corporate headshots.'],
            ['id' => 3, 'category_id' => 1, 'name' => 'Editorial / Creative Makeup', 'price' => 1800, 'duration_minutes' => 90, 'image' => 'images/preview.webp', 'description' => 'Avant-garde or fashion-focused heavy color application customized for photographic studio shoots, runway, or events.'],
            ['id' => 4, 'category_id' => 1, 'name' => 'Daily Make Up', 'price' => 135, 'duration_minutes' => 45, 'image' => 'images/images_2.webp', 'description' => 'Light and radiant makeup for day-to-day fresh looks.'],
            ['id' => 5, 'category_id' => 1, 'name' => 'Night Make Up', 'price' => 140, 'duration_minutes' => 60, 'image' => 'images/images_2.webp', 'description' => 'Bold and glamorous evening makeup look.'],
            ['id' => 6, 'category_id' => 2, 'name' => 'Simple Haircut', 'price' => 70, 'duration_minutes' => 45, 'image' => 'images/view.webp', 'description' => 'Precision trimming and styled haircut.'],
            ['id' => 7, 'category_id' => 2, 'name' => 'Hair Styling', 'price' => 30, 'duration_minutes' => 45, 'image' => 'images/view.webp', 'description' => 'Professional blowouts and custom styling.'],
            ['id' => 8, 'category_id' => 2, 'name' => 'Full Hair Color', 'price' => 100, 'duration_minutes' => 90, 'image' => 'images/view.webp', 'description' => 'Rich custom all-over hair coloration.'],
            ['id' => 9, 'category_id' => 2, 'name' => 'Protein Treatment', 'price' => 150, 'duration_minutes' => 60, 'image' => 'images/view.webp', 'description' => 'Deeply restorative hair fiber nourishment.'],
            ['id' => 10, 'category_id' => 3, 'name' => 'Manicure', 'price' => 115, 'duration_minutes' => 45, 'image' => 'images/nail.webp', 'description' => 'Nail shaping, cuticle care, and premium polish.'],
            ['id' => 11, 'category_id' => 3, 'name' => 'Pedicure', 'price' => 135, 'duration_minutes' => 60, 'image' => 'images/nail.webp', 'description' => 'Relaxing foot soak, exfoliation, and nail polish.'],
            ['id' => 12, 'category_id' => 3, 'name' => 'French Manicure', 'price' => 120, 'duration_minutes' => 50, 'image' => 'images/nail.webp', 'description' => 'Classic elegant French tips on natural nails.'],
            ['id' => 13, 'category_id' => 4, 'name' => 'Botox', 'price' => 180, 'duration_minutes' => 30, 'image' => 'images/facw.webp', 'description' => 'Wrinkle smoothing skincare therapy.'],
            ['id' => 14, 'category_id' => 4, 'name' => 'Chemical Peel', 'price' => 175, 'duration_minutes' => 45, 'image' => 'images/facw.webp', 'description' => 'Exfoliating skin renewal procedure.'],
            ['id' => 15, 'category_id' => 5, 'name' => 'Body Scrub & Smoothing', 'price' => 150, 'duration_minutes' => 60, 'image' => 'images/spa.webp', 'description' => 'Full body exfoliating scrub and moisturizer.'],
            ['id' => 16, 'category_id' => 5, 'name' => 'Aromatherapy', 'price' => 180, 'duration_minutes' => 60, 'image' => 'images/spa.webp', 'description' => 'Essential oil stress relief body therapy.'],
        ];
    }

    public function getFeaturedBookingServices(): array
    {
        return array_slice($this->getServicesWithPrices(), 0, 3);
    }

    public function getSpecialists(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Elena Rostova',
                'title' => 'Senior Bridal Artist',
                'rating' => '4.9',
                'image' => 'images/demor_.webp',
            ],
            [
                'id' => 2,
                'name' => 'Sophia Miller',
                'title' => 'Creative Director',
                'rating' => '5.0',
                'image' => 'images/df.webp',
            ],
            [
                'id' => 3,
                'name' => 'Amara Diallo',
                'title' => 'Skin & Lash Specialist',
                'rating' => '4.8',
                'image' => 'images/Bfacejpg.webp',
            ],
        ];
    }

    public function getTeamMembers(): array
    {
        return [
            ['name' => 'Chantelle Ramos', 'title' => 'Hairdresser', 'image' => 'images/preview.webp'],
            ['name' => 'Chariz Mae Lopez', 'title' => 'Hairdresser', 'image' => 'images/mem.webp'],
            ['name' => 'Katrina ellaine Mendoza', 'title' => 'Hairdresser', 'image' => 'images/Gem.webp'],
            ['name' => 'Claire Bautista', 'title' => 'Hairdresser', 'image' => 'images/rem.webp'],
            ['name' => 'Rheanne Villanueva', 'title' => 'Hairdresser', 'image' => 'images/ew.webp'],
            ['name' => 'Lin Delos Santos', 'title' => 'Hairdresser', 'image' => 'images/em.webp'],
        ];
    }

    public function getTestimonials(): array
    {
        return [
            [
                'author' => 'Happy client',
                'content' => 'Visiting S Beauty Salon is always a highlight of my month! The team truly understands how to bring out your natural beauty, and every treatment leaves me feeling refreshed, confident, and pampered.',
            ],
            [
                'author' => 'Grateful client',
                'content' => 'Absolute perfection every single time! The staff is so talented and welcoming, and I always leave S Beauty Salon feeling like the best, most radiant version of myself.',
            ],
        ];
    }

    public function getAvailableSlots(int $staffId = 1, string $date = '2026-10-14', int $serviceId = 1, array $sessionBookedSlots = []): array
    {
        $standardSlots = [
            ['time' => '09:00:00', 'formatted' => '09:00 AM'],
            ['time' => '10:30:00', 'formatted' => '10:30 AM'],
            ['time' => '13:00:00', 'formatted' => '01:00 PM'],
            ['time' => '14:30:00', 'formatted' => '02:30 PM'],
            ['time' => '16:00:00', 'formatted' => '04:00 PM'],
            ['time' => '17:30:00', 'formatted' => '05:30 PM'],
        ];

        $slots = [];
        foreach ($standardSlots as $slot) {
            $slotKey = sprintf('%d_%s_%s', $staffId, $date, $slot['time']);
            $isBooked = in_array($slotKey, $sessionBookedSlots, true);

            $slots[] = [
                'time' => $slot['time'],
                'formatted' => $slot['formatted'],
                'available' => !$isBooked,
            ];
        }

        return $slots;
    }
}

