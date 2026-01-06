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
use App\Models\AdminMessage;


class BatchMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $action, $usersID, $message, $extra, $adminMessageId;



    /**
     * Create a new job instance.
     */
    public function __construct($action, $usersID, $message, $extra = [], $adminMessageId = null)
    {
        $this->action = $action;
        $this->usersID = $usersID;
        $this->message = $message;
        $this->extra = $extra;
        $this->adminMessageId = $adminMessageId;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $adminMessage = null;
        if ($this->adminMessageId) {
            $adminMessage = AdminMessage::find($this->adminMessageId);
            if ($adminMessage) {
                $adminMessage->update(['status' => 'processing']);
            }
        }

        try {
            Log::info("Handling batch message with action: {$this->action}");
            $telegramService = App::make(\App\Services\TelegramService::class);

            $sentCount = 0;
            foreach ($this->usersID as $userId) {
                $response = null;
                if ($adminMessage && $adminMessage->image_path) {
                    // Send photo directly from local storage
                    $localPath = public_path($adminMessage->image_path);
                    Log::info("Attempting to send photo from: $localPath for user: $userId");
                    if (file_exists($localPath)) {
                        $response = $telegramService->sendPhotoFile($userId, $localPath, $adminMessage->message);
                    } else {
                        Log::error("Photo file not found at: $localPath");
                        // Fallback to text if photo missing
                        $response = $telegramService->sendMessage($userId, $this->message, $this->extra);
                    }
                } else {
                    // Send text
                    $response = $telegramService->sendMessage($userId, $this->message, $this->extra);
                }

                if (isset($response['ok']) && $response['ok']) {
                    $sentCount++;
                } else {
                    Log::error("Failed to send message to $userId: " . json_encode($response));
                }

                if ($adminMessage && $sentCount % 10 == 0) {
                    $adminMessage->update(['sent_users' => $sentCount]);
                }

                // Small delay to avoid hitting Telegram rate limits
                usleep(50000); // 0.05 seconds
            }

            if ($adminMessage) {
                $adminMessage->update([
                    'status' => 'completed',
                    'sent_users' => $sentCount
                ]);
            }

            Log::info("Batch message sent successfully to users: " . implode(', ', $this->usersID));

        } catch (\Exception $e) {
            if ($adminMessage) {
                $adminMessage->update(['status' => 'failed']);
            }
            Log::error("Error handling batch message: " . $e->getMessage());
        }
    }
}
