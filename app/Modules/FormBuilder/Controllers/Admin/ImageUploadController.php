<?php

namespace App\Modules\FormBuilder\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Requests\DeleteFormImageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    /**
     * Upload an image for use in form builder image blocks.
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('image') ?: 'Invalid image upload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension();
            $filename = 'form_image_'.Str::random(20).'.'.$extension;

            // Store in private disk and serve via authenticated /files/{path} route
            $path = $file->storeAs('form_images', $filename, 'private');
            $url = '/files/'.collect(explode('/', ltrim($path, '/')))
                ->map(fn (string $segment) => rawurlencode($segment))
                ->implode('/');

            return response()->json([
                'success' => true,
                'url' => $url,
                'path' => $path,
            ]);
        } catch (\Exception $e) {
            \Log::error('Image upload failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image. Please try again.',
            ], 500);
        }
    }

    /**
     * Delete an uploaded image.
     */
    public function delete(DeleteFormImageRequest $request): JsonResponse
    {
        try {
            $path = $request->validated()['path'];

            // Ensure it's a form_images path for security
            if (! Str::startsWith($path, 'form_images/')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid image path.',
                ], 400);
            }

            if (Storage::disk('private')->exists($path)) {
                Storage::disk('private')->delete($path);
            }

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Image deletion failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image.',
            ], 500);
        }
    }
}
