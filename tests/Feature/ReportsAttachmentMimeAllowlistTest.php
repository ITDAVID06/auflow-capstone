<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Extends ReportsAttachmentAccessTest to reuse the helper methods.
class ReportsAttachmentMimeAllowlistTest extends ReportsAttachmentAccessTest
{
    public function test_html_mime_is_served_as_octet_stream_with_attachment_disposition(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user, 'text/html', 'evil.html');
        Storage::disk('local')->put($attachment->file_path, '<script>alert(1)</script>');

        $response = $this->actingAs($user)
            ->get(route('reports.attachments.preview', $attachment->id));

        $response->assertOk();
        $this->assertStringStartsWith('application/octet-stream', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_javascript_mime_is_served_as_octet_stream(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user, 'application/javascript', 'evil.js');
        Storage::disk('local')->put($attachment->file_path, 'alert(1)');

        $response = $this->actingAs($user)
            ->get(route('reports.attachments.preview', $attachment->id));

        $response->assertOk();
        $this->assertStringStartsWith('application/octet-stream', $response->headers->get('Content-Type') ?? '');
    }

    public function test_pdf_mime_is_served_inline(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user, 'application/pdf', 'report.pdf');
        Storage::disk('local')->put($attachment->file_path, '%PDF-1.4');

        $response = $this->actingAs($user)
            ->get(route('reports.attachments.preview', $attachment->id));

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_image_jpeg_mime_is_served_inline(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user, 'image/jpeg', 'photo.jpg');
        Storage::disk('local')->put($attachment->file_path, 'JFIF');

        $response = $this->actingAs($user)
            ->get(route('reports.attachments.preview', $attachment->id));

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_x_content_type_options_nosniff_present_on_preview(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user, 'image/png', 'img.png');
        Storage::disk('local')->put($attachment->file_path, 'PNG');

        $response = $this->actingAs($user)
            ->get(route('reports.attachments.preview', $attachment->id));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
