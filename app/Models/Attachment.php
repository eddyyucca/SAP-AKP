<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = ['path', 'original_name', 'mime', 'size', 'uploaded_by'];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function humanSize(): string
    {
        return $this->size >= 1048576 ? number_format($this->size / 1048576, 1).' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }

    protected static function booted(): void
    {
        static::deleting(fn (Attachment $a) => Storage::disk('local')->delete($a->path));
    }
}
