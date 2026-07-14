<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_creation_creates_db_records_and_drive_folder(): void
    {
        Admin::create([
            'email' => 'admin@test.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->mockStorageProviderForFolderCreation();

        $this->postWithCsrf('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postWithCsrf('/admin/events', [
            'title' => 'Wedding Test',
            'description' => 'A test wedding',
            'event_type' => 'Wedding',
            'allow_photos' => true,
            'allow_videos' => true,
            'allow_voice' => true,
            'allow_messages' => true,
            'client_name' => 'Test Client',
            'client_email' => 'client@test.com',
            'client_password' => 'ClientPass123!',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('events', [
            'title' => 'Wedding Test',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('clients', [
            'name' => 'Test Client',
            'email' => 'client@test.com',
        ]);

        $event = Event::first();
        $this->assertNotNull($event->storage_root_folder_id);
        $this->assertNotNull($event->upload_token);
        $this->assertNotNull($event->upload_slug);
    }

    public function test_event_creation_requires_title(): void
    {
        Admin::create([
            'email' => 'admin@test.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->postWithCsrf('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postWithCsrf('/admin/events', [
            'event_type' => 'Wedding',
            'client_name' => 'Test Client',
            'client_email' => 'client@test.com',
            'client_password' => 'ClientPass123!',
        ]);

        $response->assertSessionHasErrors('title');
    }
}
