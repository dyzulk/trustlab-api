<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewInquiryNotification;

class InquiryNotificationTest extends TestCase
{
    // use RefreshDatabase; // Don't wipe the DB, we want to test with existing or created user

    public function test_public_inquiry_sends_notification_to_admin()
    {
        // 1. Ensure Admin exists
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::factory()->create([
                'role' => 'admin',
                'email' => 'admin_test_'.time().'@example.com',
            ]);
        }

        // Clear previous notifications for clean test?
        // $admin->notifications()->delete(); 

        $initialCount = $admin->notifications()->count();

        // 2. Send Request
        $response = $this->postJson('/api/public/inquiries', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Test Inquiry Subject',
            'message' => 'This is a test message for notification.',
            'category' => 'General',
        ]);

        $response->assertStatus(201);

        // 3. Verify Notification in Database
        // We re-fetch admin to see new notifications
        $finalCount = $admin->notifications()->count();
        
        $this->assertTrue($finalCount > $initialCount, "Notification count did not increase. Initial: $initialCount, Final: $finalCount");

        $latest = $admin->notifications()->latest()->first();
        $this->assertEquals('App\Notifications\NewInquiryNotification', $latest->type);
        $this->assertEquals('New inquiry from John Doe: Test Inquiry Subject', $latest->data['message']);
    }
}
