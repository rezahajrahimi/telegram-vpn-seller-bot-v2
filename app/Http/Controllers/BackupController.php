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
    /**
     * گرفتن بکاپ از کل دیتابیس
     */
    public function createBackup()
    {
        try {
            // قبل از ایجاد فایل backup
            $backupPath = storage_path('app/backups');

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
        'mariadb-dump --skip-set-charset --no-tablespaces -h %s -u %s -p%s %s > %s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.database'),
                storage_path('app/backups/' . $filename)
            );
        // $command = sprintf(
        //     'mysqldump --skip-set-charset -h %s -u %s -p%s %s > %s',
        //         config('database.connections.mysql.username'),
        //         config('database.connections.mysql.password'),
        //         config('database.connections.mysql.database'),
        //         storage_path('app/backups/' . $filename)
        //     );

            // اجرای دستور
            exec($command);

            // ذخیره فایل در storage
            if (file_exists(storage_path('app/backups/' . $filename))) {
                return response()->download(storage_path('app/backups/' . $filename));
            }

            return response()->json([
                'status' => 'error',
                'message' => 'خطا در ایجاد فایل بکاپ'
            ], 500);

        } catch (Exception $e) {
            \Log::error('خطا در ایجاد فایل بکاپ: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * بازیابی اطلاعات از فایل بکاپ
     */
    public function restoreBackup(Request $request)
    {
        try {
            if (!$request->hasFile('backup_file')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'فایل بکاپ یافت نشد'
                ], 400);
            }

            // گرفتن بکاپ از دیتابیس فعلی قبل از بازیابی
            $backupBeforeRestore = 'backup_before_restore_' . Carbon::now()->format('Y-m-d_H-i-s') . '.sql';
            $backupCommand = sprintf(
                'mariadb -u%s -p%s %s > %s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.database'),
                storage_path('app/backups/' . $backupBeforeRestore)
            );
            // $backupCommand = sprintf(
            //     'mysqldump -u%s -p%s %s > %s',
            //     config('database.connections.mysql.username'),
            //     config('database.connections.mysql.password'),
            //     config('database.connections.mysql.database'),
            //     storage_path('app/backups/' . $backupBeforeRestore)
            // );
            exec($backupCommand);

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

            $file = $request->file('backup_file');
            $filename = 'restore_' . time() . '.sql';
            
            // ذخیره موقت فایل
            $file->storeAs('backups/temp', $filename);
            
            // دستور mysql برای بازیابی
            $command = sprintf(
                'mysql -u%s -p%s %s < %s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.database'),
                storage_path('app/backups/temp/' . $filename)
            );

            exec($command);

            // پاک کردن فایل موقت
            Storage::delete('backups/temp/' . $filename);

            return response()->json([
                'status' => 'success',
                'message' => 'بازیابی اطلاعات با موفقیت انجام شد',
                'backup_file' => $backupBeforeRestore
            ]);

        } catch (Exception $e) {
            \Log::error('خطا در بازیابی اطلاعات: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // private function createDatabaseBackup(): string 
    // {
    //     $dbName = config('database.connections.mysql.database');
    //     $username = config('database.connections.mysql.username'); 
    //     $password = config('database.connections.mysql.password');
    //     $host = config('database.connections.mysql.host');

    //     // Add --skip-set-charset to avoid sandbox mode error
    //     $command = sprintf(
    //         'mysqldump --skip-set-charset -h %s -u %s -p%s %s > %s',
    //         $host,
    //         $username, 
    //         $password,
    //         $dbName,
    //         storage_path('app/backups/database_' . date('Y-m-d_H-i-s') . '.sql')
    //     );

    //     exec($command);
        
    //     return 'database_' . date('Y-m-d_H-i-s') . '.sql';
    // }

    // private function restoreDatabaseBackup(string $backupFile): void
    // {
    //     $dbName = config('database.connections.mysql.database');
    //     $username = config('database.connections.mysql.username'); 
    //     $password = config('database.connections.mysql.password');
    //     $host = config('database.connections.mysql.host');

    //     // Add --skip-set-charset to avoid sandbox mode error
    //     $command = sprintf(
    //         'mysql --skip-set-charset -h %s -u %s -p%s %s < %s',
    //         $host,
    //         $username,
    //         $password,
    //         $dbName,
    //         storage_path('app/backups/' . $backupFile)
    //     );

    //     exec($command);
    // }
} 