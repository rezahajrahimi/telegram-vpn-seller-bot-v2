<?php

namespace Tests\Unit;

use App\Http\Controllers\BackupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class BackupDownloadCorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(storage_path('app/public/backups'));
    }

    protected function tearDown(): void
    {
        $file = storage_path('app/public/backups/backup_2026-08-15_09-26-27.sql');
        if (is_file($file)) {
            unlink($file);
        }
        parent::tearDown();
    }

    public function test_download_backup_sets_cors_headers_for_query_filename(): void
    {
        $filename = 'backup_2026-08-15_09-26-27.sql';
        $path = storage_path('app/public/backups/' . $filename);
        file_put_contents($path, "-- test dump\n");

        $controller = new BackupController();
        $request = Request::create('/api/downloadBackup', 'GET', ['filename' => $filename]);
        $response = $controller->downloadBackup($request);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame($path, $response->getFile()->getPathname());
    }

    public function test_download_backup_accepts_filename_with_sql_extension_in_path(): void
    {
        $filename = 'backup_2026-08-15_09-26-27.sql';
        $path = storage_path('app/public/backups/' . $filename);
        file_put_contents($path, "-- test dump\n");

        $controller = new BackupController();
        $request = Request::create('/api/downloadBackup/' . $filename, 'GET');
        $response = $controller->downloadBackup($request, $filename);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
