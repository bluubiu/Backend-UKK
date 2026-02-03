<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Loan;
use App\Models\ScoreLog;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecoverScoresCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scores:recover';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover user scores (+5) if they have no loans in the past month (upto 100)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting score recovery process...');

        // Targeted users: Score < 100
        $users = User::where('score', '<', 100)->get();
        $recoveredCount = 0;

        foreach ($users as $user) {
            // Check if user has any loans in the past month
            // "Loans in past month" means loans CREATED or RETURNED in the past 30 days?
            // Usually "no activity" means no active loans or new loans. 
            // "Tidak ada peminjaman" usually means they didn't borrow anything.
            // Let's check for loans created >= 30 days ago.
            
            $hasLoans = Loan::where('user_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subMonth())
                ->exists();

            if (!$hasLoans) {
                // Check if user has active loans currently? (Should we recover if they have a long-term loan?)
                // Assumption: "Tidak ada peminjaman" means "Not engaging in borrowing activity" OR "Clean record".
                // Safest bet: No loans created in last month.
                
                try {
                    DB::beginTransaction();

                    $oldScore = $user->score;
                    // Grant +5, but cap at 100 (Recovery limit, though global max is 120. Recovery usually brings back to baseline)
                    // Requirements say "Auto-recovery". Usually recovery is to normal. 
                    // Let's increment +5.
                    
                    // We use the helper, but we might want to cap recovery at 100 specifically?
                    // "Minimum score 0" "Batas score maks 120".
                    // "Auto-recovery: +5 poin per bulan".
                    // Usually recovery implies getting back to good standing. 
                    // I'll stick to +5, complying with max 120 (User model handles it).
                    // BUT, typically recovery stops at 100. I will implement logic: ONLY apply if score < 100.
                    
                    $user->updateScore(5);
                    
                    if ($user->score > $oldScore) {
                        $actualParam = $user->score - $oldScore;
                        
                        ScoreLog::create([
                            'user_id' => $user->id,
                            'score_change' => $actualParam,
                            'reason' => 'Auto recovery bulanan (Tidak ada peminjaman 1 bulan)'
                        ]);

                        Notification::create([
                            'user_id' => $user->id,
                            'type' => 'score_increase',
                            'title' => 'Score Recovery',
                            'message' => 'Selamat! Score Anda bertambah 5 poin karena tidak ada aktivitas peminjaman bulan ini.',
                            'data' => ['score_change' => $actualParam, 'new_score' => $user->score]
                        ]);

                        $recoveredCount++;
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Failed to recover score for user {$user->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Score recovery completed. {$recoveredCount} users recovered.");
    }
}
