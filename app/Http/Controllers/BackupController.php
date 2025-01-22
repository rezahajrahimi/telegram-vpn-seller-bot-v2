<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;

class BackupController extends Controller
{
    /**
     * گرفتن بکاپ از کل دیتابیس
     */
    public function createBackup()
    {
        try {
            // نام فایل بکاپ با تاریخ و زمان
            $filename = 'backup_' . Carbon::now()->format('Y-m-d_H-i-s') . '.sql';
            
            // دستور mysqldump برای گرفتن بکاپ
            $command = sprintf(
                'mysqldump -u%s -p%s %s > %s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.database'),
                storage_path('app/backups/' . $filename)
            );

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
                'mysqldump -u%s -p%s %s > %s',
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.database'),
                storage_path('app/backups/' . $backupBeforeRestore)
            );
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
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
} 