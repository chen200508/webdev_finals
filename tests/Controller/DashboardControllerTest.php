<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    public function testDashboardPagesIncludeDataTableAndNinetyPercentLayout(): void
    {
        $client = static::createClient();

        $pages = [
            '/dashboard/services' => 'Category',
            '/dashboard/appointments' => 'Status',
            '/dashboard/customers' => 'Search customers...',
            '/dashboard/users' => 'Search users...',
            '/dashboard/activity-logs' => 'Search activity logs...',
        ];

        foreach ($pages as $url => $expectedFilterOrPlaceholder) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful();

            $content = (string) $client->getResponse()->getContent();
            $this->assertStringContainsString('https://cdn.datatables.net/v/dt/dt-3.1.5/datatables.min.css', $content);
            $this->assertStringContainsString('https://cdn.datatables.net/v/dt/dt-3.1.5/datatables.min.js', $content);
            $this->assertStringContainsString('data-datatable', $content);
            $this->assertStringContainsString('width: 90%; max-width: none;', $content);
            $this->assertStringContainsString('.desk-logo { width: 150px; height: auto; display: block; }', $content);
            $this->assertStringContainsString('secondary-', $content);
            $this->assertStringContainsString($expectedFilterOrPlaceholder, $content);
        }

        // Also check Overview page has 90% layout width
        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('width: 90%; max-width: none;', (string) $client->getResponse()->getContent());
    }
}

