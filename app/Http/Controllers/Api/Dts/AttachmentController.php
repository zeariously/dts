<?php

namespace App\Http\Controllers\Api\Dts;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function show(Request $request, int $file)
    {
        if (! Schema::hasTable('dts_document_files')) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found.',
            ], 404);
        }

        $fileRecord = DB::table('dts_document_files')
            ->where('id', $file)
            ->first();

        if (! $fileRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found.',
            ], 404);
        }

        $storedPath = trim((string) ($fileRecord->path ?? ''));

        if ($storedPath === '') {
            return response()->json([
                'success' => false,
                'message' => 'Attachment file path is missing.',
            ], 404);
        }

        $storedPath = str_replace('\\', '/', $storedPath);
        $storedPath = preg_replace(
            '#^(?:storage/|public/)+#i',
            '',
            $storedPath
        );

        $storedPath = ltrim($storedPath, '/');

        if (
            $storedPath === ''
            || str_contains($storedPath, '../')
            || str_starts_with($storedPath, '..')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid attachment file path.',
            ], 404);
        }

        if (! Storage::disk('public')->exists($storedPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment file not found in storage.',
            ], 404);
        }

        $absolutePath = Storage::disk('public')->path($storedPath);

        $fileName = trim(
            (string) (
                $fileRecord->original_name
                ?? $fileRecord->stored_name
                ?? basename($absolutePath)
            )
        );

        if ($fileName === '') {
            $fileName = basename($absolutePath);
        }

        $fileName = str_replace(
            ["\r", "\n", '"'],
            '',
            $fileName
        );

        $mimeType = trim(
            (string) ($fileRecord->mime_type ?? '')
        );

        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        $disposition = $request->boolean('download')
            ? 'attachment'
            : 'inline';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' =>
                $disposition . '; filename="' . $fileName . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}