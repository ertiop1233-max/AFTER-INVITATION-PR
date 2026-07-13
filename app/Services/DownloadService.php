<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use ZipStream\Option\Archive as ArchiveOptions;
use ZipStream\ZipStream;

class DownloadService
{
    public function __construct(
        private readonly StorageService $storageService,
    ) {}

    public function streamSubmissionZip(Submission $submission, string $outputName = null): void
    {
        $this->disableOutputBuffering();

        $zipName = $outputName ?? "submission_{$submission->id}.zip";

        $options = new ArchiveOptions();
        $options->setSendHttpHeaders(true);
        $options->setOutputName($zipName);
        $options->setFlushOutput(true);

        $zip = new ZipStream($options);

        $uploadedMedia = $submission->media()
            ->where('status', Media::STATUS_UPLOADED)
            ->get();

        foreach ($uploadedMedia as $media) {
            $stream = $this->storageService->getFileStream($media->storage_id);
            $zip->addFileFromStream($media->stored_filename, $stream);
        }

        if ($submission->hasVoice() && $submission->voice_storage_id) {
            $stream = $this->storageService->getFileStream($submission->voice_storage_id);
            $extension = pathinfo($submission->voice_storage_path, PATHINFO_EXTENSION);
            $zip->addFileFromStream("voice_recording.{$extension}", $stream);
        }

        if ($submission->hasMessage()) {
            $zip->addFile('message.txt', $submission->written_message);
        }

        $manifest = $this->buildManifest($submission, $uploadedMedia);
        $zip->addFile('_manifest.txt', $manifest);

        $zip->finish();
    }

    public function streamEventZip(Event $event): void
    {
        $this->disableOutputBuffering();

        $zipName = "event_{$event->upload_slug}.zip";

        $options = new ArchiveOptions();
        $options->setSendHttpHeaders(true);
        $options->setOutputName($zipName);
        $options->setFlushOutput(true);

        $zip = new ZipStream($options);

        $submissions = $event->submissions()
            ->where('status', Submission::STATUS_COMPLETED)
            ->with(['media' => function ($query) {
                $query->where('status', Media::STATUS_UPLOADED);
            }])
            ->get();

        foreach ($submissions as $submission) {
            $safeName = $this->sanitizeForZip($submission->contributor_name);

            foreach ($submission->media as $media) {
                $stream = $this->storageService->getFileStream($media->storage_id);
                $zip->addFileFromStream("{$safeName}/{$media->stored_filename}", $stream);
            }

            if ($submission->hasVoice() && $submission->voice_storage_id) {
                $stream = $this->storageService->getFileStream($submission->voice_storage_id);
                $extension = pathinfo($submission->voice_storage_path, PATHINFO_EXTENSION);
                $zip->addFileFromStream("{$safeName}/voice_recording.{$extension}", $stream);
            }

            if ($submission->hasMessage()) {
                $zip->addFile("{$safeName}/message.txt", $submission->written_message);
            }
        }

        $manifest = $this->buildEventManifest($event, $submissions);
        $zip->addFile('_manifest.txt', $manifest);

        $zip->finish();
    }

    public function canDownloadEventZip(Event $event): bool
    {
        $maxFiles = config('memoryvault.download.max_event_zip_files', 500);
        $maxBytes = config('memoryvault.download.max_event_zip_bytes', 5368709120);

        $mediaCount = $event->media()
            ->where('status', Media::STATUS_UPLOADED)
            ->count();

        $totalBytes = $event->media()
            ->where('status', Media::STATUS_UPLOADED)
            ->sum('file_size_bytes');

        $totalBytes += $event->submissions()
            ->where('status', Submission::STATUS_COMPLETED)
            ->whereNotNull('voice_storage_id')
            ->sum('voice_size_bytes');

        return $mediaCount <= $maxFiles && $totalBytes <= $maxBytes;
    }

    public function streamSingleFile(Media $media): void
    {
        $this->disableOutputBuffering();

        $stream = $this->storageService->getFileStream($media->storage_id);

        header('Content-Type: ' . $media->mime_type);
        header('Content-Disposition: attachment; filename="' . $media->original_filename . '"');
        header('Content-Length: ' . $media->file_size_bytes);

        while (!$stream->eof()) {
            echo $stream->read(8192);
            flush();
        }
    }

    private function buildManifest(Submission $submission, $uploadedMedia): string
    {
        $lines = [];
        $lines[] = "Memory Vault - Submission Manifest";
        $lines[] = "==================================";
        $lines[] = "Submission ID: {$submission->id}";
        $lines[] = "Contributor: {$submission->contributor_name}";
        $lines[] = "Submitted: {$submission->submitted_at}";
        $lines[] = "Total Photos: {$submission->total_photos}";
        $lines[] = "Total Videos: {$submission->total_videos}";
        $lines[] = "Total Size: {$this->formatBytes($submission->total_size_bytes)}";
        $lines[] = "";
        $lines[] = "Files:";

        foreach ($uploadedMedia as $media) {
            $lines[] = "  - {$media->stored_filename} ({$media->media_type}, {$this->formatBytes($media->file_size_bytes)})";
        }

        if ($submission->hasVoice()) {
            $lines[] = "  - voice_recording (audio, {$this->formatBytes($submission->voice_size_bytes)})";
        }

        if ($submission->hasMessage()) {
            $lines[] = "";
            $lines[] = "Message included: message.txt";
        }

        return implode("\n", $lines) . "\n";
    }

    private function buildEventManifest(Event $event, $submissions): string
    {
        $lines = [];
        $lines[] = "Memory Vault - Event Manifest";
        $lines[] = "==============================";
        $lines[] = "Event: {$event->title}";
        $lines[] = "Total Submissions: {$submissions->count()}";
        $lines[] = "Generated: " . now()->toDateTimeString();
        $lines[] = "";
        $lines[] = "Submissions:";

        foreach ($submissions as $submission) {
            $lines[] = "  - {$submission->contributor_name} (ID: {$submission->id}, {$submission->total_photos} photos, {$submission->total_videos} videos)";
        }

        return implode("\n", $lines) . "\n";
    }

    private function sanitizeForZip(string $name): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_\-\s]/', '', $name);
        return trim($sanitized) ?: 'contributor';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function disableOutputBuffering(): void
    {
        header('Content-Encoding: identity');
        @ini_set('zlib.output_compression', 'Off');

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        @set_time_limit(0);
    }
}
