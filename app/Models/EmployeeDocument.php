<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'title',
        'category',
        'disk',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'is_confidential',
        'expires_at',
        'uploaded_by',
    ];

    protected $casts = [
        'is_confidential' => 'boolean',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['file_path'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getStoragePath(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        // If already a full path stored, return as-is.
        return $this->file_path;
    }

    public function downloadUrl(): string
    {
        // Never expose direct public URLs; downloads flow through an
        // authorized controller. This is a placeholder helper.
        return route('admin.hr.documents.show', $this->id);
    }

    public function destroyWithFile(): bool
    {
        $path = $this->file_path;
        $disk = $this->disk ?: 'local';
        $deleted = parent::delete();

        if ($deleted && $path && ! Str::startsWith($path, ['http://', 'https://'])) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } catch (\Throwable) {
                // File removal failures must not block deletion of the
                // document record.
            }
        }

        return $deleted;
    }
}
