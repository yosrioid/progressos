<?php

namespace App\Console\Commands;

use App\Mail\NotificationDigest;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationDigest extends Command
{
    protected $signature = 'notifications:send-digest {--user= : Send only for this user ID}';

    protected $description = 'Email unread notification digest to users';

    public function handle(): int
    {
        $query = User::whereNotNull('email');

        if ($userId = $this->option('user')) {
            $query->where('id', $userId);
        }

        $sent = 0;

        $query->lazyById()->each(function (User $user) use (&$sent) {
            $unread = InAppNotification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->whereDate('created_at', today())
                ->orderBy('created_at')
                ->get();

            if ($unread->isEmpty()) {
                return;
            }

            try {
                Mail::to($user->email)->queue(new NotificationDigest($user, $unread));
                $sent++;
                $this->line("Queued digest for {$user->email} ({$unread->count()} notifications)");
            } catch (\Throwable $e) {
                Log::error('Failed to queue notification digest', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        });

        $this->info("Digest queued for {$sent} user(s).");

        return self::SUCCESS;
    }
}
