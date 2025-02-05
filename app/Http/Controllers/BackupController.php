<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{

    public function __construct()
    {
        $this->telegramBot = app('telegram_bot');
    }


    /**
     * گرفتن بکاپ از کل دیتابیس
     */
    public function createBackup()
    {
        try {
                    // Create symbolic link if not exists
            if (!file_exists(public_path('storage'))) {
                \Artisan::call('storage:link');
            }
                    // Create symbolic link if not exists
            if (!file_exists(public_path('storage'))) {
                \Artisan::call('storage:link');
            }
            // قبل از ایجاد فایل backup
            $backupPath = storage_path('app/public/backups');

            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0775, true);

                // تنظیم دسترسی‌ها در سیستم‌عامل‌های یونیکس
                if (PHP_OS !== 'WINNT') {
                    chmod($backupPath, 0775);

                    // اگر در محیط لینوکس هستید و می‌خواهید مالکیت را هم تغییر دهید
                    // $user = get_current_user();
                    // chown($backupPath, $user);
                }
            }

            // نام فایل بکاپ با تاریخ و زمان
            $filename = 'backup_' . Carbon::now()->format('Y-m-d_H-i-s') . '.sql';

            // دستور mysqldump برای گرفتن بکاپ
            $command = sprintf(
                'mysqldump --skip-set-charset -h %s -u %s -p%s %s > %s',
                config('database.connections.mysql.host'),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.database'),
                storage_path('app/public/backups/' . $filename)
            );

            // اجرای دستور
            exec($command);

            // ذخیره فایل در storage
            if (file_exists(storage_path('app/public/backups/' . $filename))) {
                // return url to download file

                return response()->json([
                    'status' => 'success',
                    'message' => 'فایل بکاپ با موفقیت ایجاد شد',
                    'url' => url('storage/backups/' . $filename)
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'خطا در ایجاد فایل بکاپ'
            ], 500);

       } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function createBackupAndReturnZipFile()
    {
        try {
                    // Create symbolic link if not exists
            if (!file_exists(public_path('storage'))) {
                \Artisan::call('storage:link');
            }
                    // Create symbolic link if not exists
            if (!file_exists(public_path('storage'))) {
                \Artisan::call('storage:link');
            }
            // قبل از ایجاد فایل backup
            $backupPath = storage_path('app/public/backups');

            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0775, true);

                // تنظیم دسترسی‌ها در سیستم‌عامل‌های یونیکس
                if (PHP_OS !== 'WINNT') {
                    chmod($backupPath, 0775);

                    // اگر در محیط لینوکس هستید و می‌خواهید مالکیت را هم تغییر دهید
                    // $user = get_current_user();
                    // chown($backupPath, $user);
                }
            }

            // نام فایل بکاپ با تاریخ و زمان
            $filename = 'backup_' . Carbon::now()->format('Y-m-d_H-i-s') . '.sql';

            // دستور mysqldump برای گرفتن بکاپ
            $command = sprintf(
                'mysqldump --skip-set-charset -h %s -u %s -p%s %s > %s',
                config('database.connections.mysql.host'),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.database'),
                storage_path('app/public/backups/' . $filename)
            );

            // اجرای دستور
            exec($command);

            // ذخیره فایل در storage
            if (file_exists(storage_path('app/public/backups/' . $filename))) {
               // تغییر این قسمت
               $zipPath = storage_path('app/public/backups/' . $filename . '.zip');
               $zip = new \ZipArchive();
               $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
               $zip->addFile(storage_path('app/public/backups/' . $filename), $filename);
               $zip->close();
               return $zipPath; // برگرداندن مسیر فایل به جای محتوای آن
            }

            return null;

       } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return null;
        }
    }


    /**
     * بازیابی اطلاعات از فایل بکاپ
     */
    public function restoreBackup(Request $request)
    {
        try {
            // بررسی وجود فایل در URL
            $backupUrl = $request->input('backup_url');
            if (!$backupUrl && !$request->hasFile('backup_file')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'فایل بکاپ یافت نشد'
                ], 400);
            }

            // Create symbolic link if not exists
            if (!file_exists(public_path('storage'))) {
                \Artisan::call('storage:link');
            }

            // حذف تمام جداول موجود
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            // گرفتن لیست تمام جداول
            $tables = DB::select('SHOW TABLES');
            $dbName = 'Tables_in_' . config('database.connections.mysql.database');

            // حذف تک تک جداول
            foreach ($tables as $table) {
                DB::statement('DROP TABLE IF EXISTS ' . $table->$dbName);
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            // ایجاد دایرکتوری temp اگر وجود نداشته باشد
            $tempPath = storage_path('app/public/backups/temp');
            if (!File::exists($tempPath)) {
                File::makeDirectory($tempPath, 0775, true);
            }

            // اگر URL ارسال شده باشد، فایل را از مسیر storage کپی می‌کنیم
            if ($backupUrl) {
                $path = parse_url($backupUrl, PHP_URL_PATH);
                $filename = basename($path);
                $sourcePath = storage_path('app/public/backups/' . $filename);

                if (!file_exists($sourcePath)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'فایل بکاپ در مسیر مشخص شده یافت نشد'
                    ], 404);
                }

                copy($sourcePath, $tempPath . '/' . $filename);
            } else {
                $file = $request->file('backup_file');
                if (!$file) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'فایل آپلود شده معتبر نیست'
                    ], 400);
                }
                $filename = 'restore_' . time() . '.sql';
                $file->move($tempPath, $filename);
            }

            // دستور mysql برای بازیابی - اصلاح پارامترها
            $command = sprintf(
                'mysql -h %s -u %s -p%s %s < %s',
                config('database.connections.mysql.host'),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.database'),
                $tempPath . '/' . $filename
            );

            // \Log::info('File received: ' . ($file ? 'yes' : 'no'));
            // \Log::info('Command: ' . $command);

            // اجرای دستور و بررسی خطا
            $output = [];
            $returnVar = 0;
            exec($command . ' 2>&1', $output, $returnVar);

            if ($returnVar !== 0) {
                \Log::error('MySQL Error: ' . implode("\n", $output));
                throw new Exception('خطا در اجرای دستور MySQL: ' . implode("\n", $output));
            }

            // پاک کردن فایل موقت
            File::delete($tempPath . '/' . $filename);

            return response()->json([
                'status' => 'success',
                'message' => 'بازیابی اطلاعات با موفقیت انجام شد',

            ]);

        } catch (Exception $e) {
            \Log::error('خطا در بازیابی اطلاعات: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


}
