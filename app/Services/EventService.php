<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventService
{
    /**
     * Update an event and handle its relationships (images) transactionally.
     *
     * @param Event $event
     * @param array $validatedData attributes to update (name, date, etc.)
     * @param array $options Extra options from request (replace_images, images_to_delete, new_files)
     * @return bool True if changes were made, False otherwise
     * @throws \Exception
     */
    public function updateEvent(Event $event, array $validatedData, array $options = []): bool
    {
        return DB::transaction(function () use ($event, $validatedData, $options) {
            $hasChanges = false;

            // 1. Update Basic Attributes
            if (!empty($validatedData)) {
                $event->fill($validatedData);
                if ($event->isDirty()) {
                    $event->save();
                    $hasChanges = true;
                    Log::info("Event attributes updated: {$event->uuid}");
                }
            }

            // 2. Handle Image Logic
            $replaceImages = filter_var($options['replace_images'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $imagesToDelete = $options['images_to_delete'] ?? [];

            // Support both 'new_images' and 'images' keys
            $newFiles = $options['images'];

            // Filter out empty/null values and ensure we have actual UploadedFile instances
            $newFiles = array_filter($newFiles, function ($file) {
                return $file instanceof UploadedFile && $file->isValid();
            });

            Log::info("Image update request", [
                'event_uuid' => $event->uuid,
                'replace_images' => $replaceImages,
                'images_to_delete_count' => count($imagesToDelete),
                'new_files_count' => count($newFiles)
            ]);

            // A. Replace All Mode
            if ($replaceImages) {
                Log::info("Replacing all images for event: {$event->uuid}");
                if ($this->deleteAllImages($event)) {
                    $hasChanges = true;
                }
            }
            // B. Delete Specific Mode (only if not replacing)
            elseif (!empty($imagesToDelete)) {
                Log::info("Deleting specific images", ['uuids' => $imagesToDelete]);
                if ($this->deleteSpecificImages($event, $imagesToDelete)) {
                    $hasChanges = true;
                }
            }

            // C. Upload New Images
            if (!empty($newFiles)) {
                Log::info("Uploading {count} new images", ['count' => count($newFiles)]);
                $this->uploadImages($event, $newFiles, $options['alt_txt'] ?? $event->name);
                $hasChanges = true;
            }

            Log::info("Event update completed", [
                'event_uuid' => $event->uuid,
                'has_changes' => $hasChanges
            ]);

            return $hasChanges;
        });
    }

    /**
     * Delete ALL images associated with an event.
     */
    protected function deleteAllImages(Event $event): bool
    {
        $images = $event->images;
        if ($images->isEmpty()) {
            Log::info("No images to delete for event: {$event->uuid}");
            return false;
        }

        Log::info("Deleting {count} images for event: {$event->uuid}", ['count' => $images->count()]);

        foreach ($images as $image) {
            $this->permanentlyDeleteImage($image);
            $event->images()->detach($image->id);
            $image->delete();
        }

        return true;
    }

    /**
     * Delete specific images by UUID.
     */
    protected function deleteSpecificImages(Event $event, array $uuids): bool
    {
        // Handle both array and comma-separated string
        if (is_string($uuids)) {
            $uuids = array_map('trim', explode(',', $uuids));
        }

        $images = Image::whereIn('uuid', $uuids)
            ->whereHas('events', fn($q) => $q->where('events.id', $event->id))
            ->get();

        if ($images->isEmpty()) {
            Log::warning("No images found to delete", ['uuids' => $uuids]);
            return false;
        }

        Log::info("Deleting {count} specific images", ['count' => $images->count()]);

        foreach ($images as $image) {
            $this->permanentlyDeleteImage($image);
            $event->images()->detach($image->id);
            $image->delete();
        }

        return true;
    }

    /**
     * Helper to delete file from disk.
     */
    protected function permanentlyDeleteImage(Image $image): void
    {
        // Fix double slashes if present in DB
        $path = str_replace('//', '/', $image->path);

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            Log::info("Deleted image file: {$path}");
        } elseif (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
            Log::info("Deleted image file: {$image->path}");
        } else {
            Log::warning("File missing on disk: {$path}");
        }
    }

    /**
     * Upload and attach new images.
     * @param Event $event
     * @param UploadedFile[] $files
     * @param string $altText
     */
    protected function uploadImages(Event $event, array $files, string $altText): void
    {
        $uploadedCount = 0;

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                Log::warning("Invalid file upload detected, skipping");
                continue;
            }

            try {
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

                // Ensure the directory exists
                $directory = "events/{$event->uuid}";

                // Store the file
                $path = $file->storeAs($directory, $filename, 'public');

                if (!$path) {
                    Log::error("Failed to store file: {$filename}");
                    continue;
                }

                // Create image record
                $image = new Image([
                    'uuid' => Str::uuid(),
                    'uploaded_by' => Auth::id(),
                    'path' => $path,
                    'img_type' => 'event',
                    'alt_txt' => $altText
                ]);

                $image->save();
                $event->images()->attach($image->id);

                $uploadedCount++;

                Log::info("Image uploaded successfully", [
                    'filename' => $filename,
                    'path' => $path,
                    'image_uuid' => $image->uuid
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to upload image", [
                    'error' => $e->getMessage(),
                    'file' => $file->getClientOriginalName()
                ]);
            }
        }

        Log::info("Image upload completed", [
            'total_files' => count($files),
            'uploaded' => $uploadedCount
        ]);
    }
}
