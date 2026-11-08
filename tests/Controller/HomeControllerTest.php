<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function testLandingPagesLoadSuccessfully(): void
    {
        $client = static::createClient();

        $pages = [
            '/' => 'Elegance comes from being as beautiful inside as outside',
            '/services' => 'Services and prices',
            '/about' => 'We can create what you imagine',
            '/contact' => 'trunobeautysalon143@gmail.com',
            '/booking' => 'Schedule Your Elegance Experience',
        ];

        foreach ($pages as $url => $expectedText) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful();
            $content = (string) $client->getResponse()->getContent();
            $this->assertStringContainsString($expectedText, $content);
            $this->assertStringNotContainsString('Get Started', $content);
            $this->assertStringContainsString('Marcellus', $content);
            $this->assertStringContainsString('Raleway', $content);
            $this->assertStringNotContainsString('Roboto', $content);
            $this->assertStringContainsString('reveal-on-scroll', $content);
        }
    }

    public function testSlotsApiReturnsAvailableSlots(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/slots?staff_id=1&date=2026-10-14&service_id=1');

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertTrue($data['success'] ?? false);
        $this->assertCount(6, $data['slots'] ?? []);
    }

    public function testBookAppointmentFlow(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/booking');
        $this->assertResponseIsSuccessful();

        $bookingForm = $crawler->filter('#bookingForm')->form([
            'full_name' => 'Shaina Truno',
            'email' => 'shaina@example.com',
            'phone' => '+63 912 345 6789',
            'service_id' => '1',
            'staff_id' => '1',
            'appointment_date' => '2026-10-14',
            'start_time' => '10:30:00',
        ]);
        $client->submit($bookingForm);
        $this->assertResponseRedirects('/booking');

        // Verify the slot is now marked unavailable in the API
        $client->request('GET', '/api/slots?staff_id=1&date=2026-10-14&service_id=1');
        $slotData = json_decode((string) $client->getResponse()->getContent(), true);
        $tenThirtySlot = array_values(array_filter(
            $slotData['slots'] ?? [],
            static fn (array $s) => $s['time'] === '10:30:00'
        ))[0] ?? null;

        $this->assertNotNull($tenThirtySlot);
        $this->assertFalse($tenThirtySlot['available']);
    }
}

