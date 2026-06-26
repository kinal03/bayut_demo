<?php

namespace Modules\RealEstate\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImageService
{
    public function upload($file, string $folder): ?string{
        if (!$file) {
            return null;
        }

        $path = public_path($folder);

        if (!File::exists($path)) {
            File::makeDirectory($path, 0777, true, true);
        }

        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;

        // Correct way to save UploadedFile
        $file->move($path, $filename);

        return $folder . '/' . $filename;
    }

    public function uploadTemp($file, string $folder): ?string
    {
        return $this->upload($file, $folder);
    }

    public function moveFromTemp(string $tempPath, string $folder): ?string
    {
        if (empty($tempPath)) {
            return null;
        }

        $tempPath = ltrim(str_replace('\\', '/', $tempPath), '/');
        $sourcePath = public_path($tempPath);

        if (!File::exists($sourcePath)) {
            return null;
        }

        $destinationDir = public_path($folder);

        if (!File::exists($destinationDir)) {
            File::makeDirectory($destinationDir, 0777, true, true);
        }

        $filename = basename($tempPath);
        $destinationPath = $destinationDir . '/' . $filename;

        if (File::exists($destinationPath)) {
            $filename = Str::uuid() . '_' . $filename;
            $destinationPath = $destinationDir . '/' . $filename;
        }

        File::move($sourcePath, $destinationPath);

        return $folder . '/' . $filename;
    }
}