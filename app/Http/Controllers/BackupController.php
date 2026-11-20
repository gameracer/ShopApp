<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Settings → Backups: schedule, extra copy folder, "Back up now" and downloads.
 */
class BackupController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'backup_frequency' => ['required', Rule::in(BackupService::FREQUENCIES)],
            'backup_time' => ['required', 'date_format:H:i'],
            'backup_weekday' => ['required', 'integer', 'between:0,6'],
            'backup_keep' => ['required', 'integer', 'min:1', 'max:365'],
            'backup_copy_path' => ['nullable', 'string', 'max:255'],
            'backup_timezone' => ['nullable', 'timezone:all'],
        ]);

        $copy = trim((string) ($data['backup_copy_path'] ?? ''));
        if ($copy !== '') {
            if (! $this->isAbsolutePath($copy)) {
                return back()->withInput()->withErrors(['backup_copy_path' => __('Enter the full folder path, for example D:\ShopApp backups.')]);
            }
            if (! is_dir($copy) && ! @mkdir($copy, 0775, true)) {
                return back()->withInput()->withErrors(['backup_copy_path' => __('The folder :folder cannot be created. Check that the drive is plugged in.', ['folder' => $copy])]);
            }
            if (! is_writable($copy)) {
                return back()->withInput()->withErrors(['backup_copy_path' => __('ShopApp cannot write to the folder :folder.', ['folder' => $copy])]);
            }
        }

        Setting::put([
            'backup_enabled' => $request->boolean('backup_enabled') ? '1' : '0',
            'backup_on_close' => $request->boolean('backup_on_close') ? '1' : '0',
            'backup_frequency' => $data['backup_frequency'],
            'backup_time' => $data['backup_time'],
            'backup_weekday' => (string) $data['backup_weekday'],
            'backup_keep' => (string) $data['backup_keep'],
            'backup_copy_path' => $copy,
            'backup_timezone' => $data['backup_timezone'] ?? Setting::get('backup_timezone') ?? '',
        ]);

        return redirect(route('settings.edit').'#backups')->with('success', __('Backup settings saved.'));
    }

    public function store(BackupService $backups)
    {
        try {
            $path = $backups->run('manual');
        } catch (RuntimeException $e) {
            return redirect(route('settings.edit').'#backups')->withErrors(['backup' => __('Backup failed: :error', ['error' => $e->getMessage()])]);
        }

        $redirect = redirect(route('settings.edit').'#backups')->with('success', __('Backup saved: :file', ['file' => basename($path)]));

        return ($error = $backups->lastError()) ? $redirect->withErrors(['backup' => $error]) : $redirect;
    }

    public function download(BackupService $backups, string $file)
    {
        $path = $backups->path($file);
        abort_unless($path, 404);

        return response()->download($path, $file, ['Content-Type' => 'application/vnd.sqlite3']);
    }

    private function isAbsolutePath(string $path): bool
    {
        return (bool) preg_match('#^([A-Za-z]:[\\\\/]|\\\\\\\\|/)#', $path);
    }
}
