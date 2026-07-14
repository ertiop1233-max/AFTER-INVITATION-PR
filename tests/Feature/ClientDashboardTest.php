<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use App\Services\Storage\StorageProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_dashboard_only_shows_completed_submissions(): void
    {
        $this->mockStorageProviderForFolderCreation();

        $event = Event::create([
            'title' => 'Client Test',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'client-token',
            'upload_slug' => 'client-slug',
            'storage_root_folder_id' => 'fake-root',
        ]);

        $client = Client::create([
            'event_id' => $event->id,
            'name' => 'Test Client',
            'email' => 'client@test.com',
            'password_encrypted' => encrypt('ClientPassword123!'),
        ]);

        $completedSub = Submission::create([
            'event_id' => $event->id,
            'upload_session_key' => 'completed-key',
            'contributor_name' => 'Completed Guest',
            'status' => Submission::STATUS_COMPLETED,
            'storage_folder_id' => 'folder-1',
            'submitted_at' => now(),
            'total_photos' => 3,
        ]);

        $draftSub = Submission::create([
            'event_id' => $event->id,
            'upload_session_key' => 'draft-key',
            'contributor_name' => 'Draft Guest',
            'status' => Submission::STATUS_DRAFT,
            'storage_folder_id' => 'folder-2',
        ]);

        $this->withSession([
            'client_id' => $client->id,
            'event_id' => $event->id,
            'client_login_at' => now(),
        ]);

        $response = $this->get('/client/submissions');

        $response->assertOk();
        $response->assertSee('Completed Guest');
        $this->assertStringNotContainsString('Draft Guest', $response->getContent());
    }

    public function test_client_can_login_with_valid_credentials(): void
    {
        $this->mockStorageProvider();

        $event = Event::create([
            'title' => 'Login Test',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'login-token',
            'upload_slug' => 'login-slug',
            'storage_root_folder_id' => 'fake-root',
        ]);

        $password = 'ClientPassword123!';
        $client = Client::create([
            'event_id' => $event->id,
            'name' => 'Login Client',
            'email' => 'login@test.com',
            'password_encrypted' => app(\App\Services\ClientPasswordService::class)->encrypt($password),
        ]);

        $response = $this->postWithCsrf('/client/login', [
            'email' => 'login@test.com',
            'password' => $password,
        ]);

        $response->assertRedirect(route('client.dashboard'));
        $this->assertEquals($client->id, session('client_id'));
        $this->assertEquals($event->id, session('event_id'));
    }

    public function test_client_cannot_login_with_wrong_password(): void
    {
        $this->mockStorageProvider();

        $event = Event::create([
            'title' => 'Login Fail',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'fail-token',
            'upload_slug' => 'fail-slug',
            'storage_root_folder_id' => 'fake-root',
        ]);

        Client::create([
            'event_id' => $event->id,
            'name' => 'Fail Client',
            'email' => 'fail@test.com',
            'password_encrypted' => app(\App\Services\ClientPasswordService::class)->encrypt('RightPassword123!'),
        ]);

        $response = $this->postWithCsrf('/client/login', [
            'email' => 'fail@test.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertRedirect();
        $this->assertNull(session('client_id'));
    }
}