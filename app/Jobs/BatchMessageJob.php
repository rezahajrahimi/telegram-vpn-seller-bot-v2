<?php

namespace App\Jobs;

use App\Http\Controllers\HiddifyPannelController;
use App\Models\Product;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;


class BatchMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $action, $usersID, $message, $extra;



    /**
     * Create a new job instance.
     */
    public function __construct($action, $usersID, $message, $extra = [])
    {
        $this->action = $action;
        $this->usersID = $usersID;
        $this->message = $message;
        $this->extra = $extra;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $success = true;
        $adminId = env(key: 'TELEGRAM_ADMIN_ID');
        try {
            // Here you can implement the logic for handling the batch message
            // For example, sending a message to a Telegram channel or user
            Log::info("Handling batch message with action: {$this->action}");
            // You can use $this->message and $this->extra as needed
            $telegramService = App::make(\App\Services\TelegramService::class);
            foreach ($this->usersID as $userId) {
                $telegramService->sendMessage($userId, $this->message, $this->extra);
                // Small delay to avoid hitting Telegram rate limits
                usleep(50000); // 0.05 seconds
            }
            Log::info("Batch message sent successfully to users: " . implode(', ', $this->usersID));



        } catch (\Exception $e) {
            $success = false;
            Log::error("Error handling batch message: " . $e->getMessage());
        }


    }
}
