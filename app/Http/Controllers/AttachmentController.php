<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Lampiran disimpan privat; hanya yang berhak melihat laporan induknya boleh membuka. */
class AttachmentController extends Controller
{
    public function show(Attachment $attachment): StreamedResponse
    {
        $parent = $attachment->attachable;
        abort_unless($parent && $parent->canBeSeenBy(portal_user()), 403);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        $inline = $attachment->isImage() || $attachment->mime === 'application/pdf';

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], $inline ? 'inline' : 'attachment');
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        abort_unless(Access::canManage(), 403);

        $attachment->attachable?->log('attachment', 'Lampiran dihapus: '.$attachment->original_name);
        $attachment->delete();

        return back()->with('success', 'Lampiran dihapus.');
    }
}
