<?php

namespace Tests;

use App\Services\Storage\StorageProviderInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::for('login', fn ($request) => Limit::perMinute(1000)->by($request->ip()));
        RateLimiter::for('uploads', fn ($request) => Limit::perHour(10000)->by($request->ip()));
        RateLimiter::for('upload-chunks', fn ($request) => Limit::perHour(100000)->by($request->ip()));
        RateLimiter::for('submissions', fn ($request) => Limit::perHour(10000)->by($request->ip()));
        RateLimiter::for('health', fn ($request) => Limit::perHour(10000)->by($request->ip()));
    }

    protected function mockStorageProvider(): MockInterface
    {
        $mock = \Mockery::mock(StorageProviderInterface::class);
        $mock->shouldReceive('checkHealth')->andReturn(true);
        $mock->shouldReceive('getQuotaInfo')->andReturn(['limit' => 10737418240, 'usage' => 0, 'usage_in_drive' => 0]);

        $this->app->instance(StorageProviderInterface::class, $mock);

        return $mock;
    }

    protected function mockStorageProviderForFolderCreation(): MockInterface
    {
        $mock = $this->mockStorageProvider();

        $folderCounter = 0;
        $mock->shouldReceive('createFolder')
            ->andReturnUsing(function () use (&$folderCounter) {
                return 'fake-folder-id-'.(++$folderCounter);
            });

        $mock->shouldReceive('uploadSmallFile')->andReturn('fake-file-id');
        $mock->shouldReceive('createResumableUpload')->andReturn('https://fake-resumable-uri.example.com');
        $mock->shouldReceive('deleteFolder')->andReturnNull();
        $mock->shouldReceive('deleteFile')->andReturnNull();
        $mock->shouldReceive('findFileInFolderByName')->andReturn(['id' => 'fake-file-id', 'size' => 1024]);
        $mock->shouldReceive('getFileStream')->andReturn(null);
        $mock->shouldReceive('uploadChunk')->andReturn(['completed' => true, 'file_id' => 'fake-file-id', 'size' => 1024]);

        return $mock;
    }

    protected function postWithCsrf(string $uri, array $data = []): TestResponse
    {
        return $this->withSession(['_token' => 'test-token'])->post($uri, array_merge(['_token' => 'test-token'], $data));
    }

    protected function deleteWithCsrf(string $uri, array $data = []): TestResponse
    {
        return $this->withSession(['_token' => 'test-token'])->delete($uri, array_merge(['_token' => 'test-token'], $data));
    }
}
