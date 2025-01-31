<?php

namespace App\Models;

use App\Jobs\Backup\RunBackup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $type
 * @property string $name
 * @property int $server_id
 * @property int $storage_id
 * @property int $database_id
 * @property string $interval
 * @property int $keep_backups
 * @property string $status
 * @property Server $server
 * @property StorageProvider $storage
 * @property Database $database
 * @property BackupFile[] $files
 */
class Backup extends AbstractModel
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'server_id',
        'storage_id',
        'database_id',
        'interval',
        'keep_backups',
        'status',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'storage_id' => 'integer',
        'database_id' => 'integer',
        'keep_backups' => 'integer',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::deleting(function (Backup $backup) {
            $backup->files()->delete();
        });
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function storage(): BelongsTo
    {
        return $this->belongsTo(StorageProvider::class, 'storage_id');
    }

    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(BackupFile::class, 'backup_id');
    }

    public function run(): void
    {
        $file = new BackupFile([
            'backup_id' => $this->id,
            'name' => Str::of($this->name)->slug().'-'.now()->format('YmdHis'),
            'status' => 'creating',
        ]);
        $file->save();

        dispatch(new RunBackup($file))->onConnection('ssh');
    }
}
