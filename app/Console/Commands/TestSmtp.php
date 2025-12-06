<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\TestMail;

class TestSmtp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:smtp {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'SMTP動作確認テスト';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $email = $this->argument('email') ?: 'murasakiiroga.suki@gmail.com';
        
        $this->info("SMTP動作確認テストを開始します...");
        $this->info("送信先: {$email}");
        $this->info("送信者: contact-jiyugaoka@lme-order.net");

        $mailers = ['xserver', 'xserver2', 'xserver3', 'smtp'];
        $success = false;
        
        foreach ($mailers as $mailer) {
            try {
                $this->info("メーラー {$mailer} で送信を試行中...");
                
                // メール送信
                Mail::mailer($mailer)->to($email)->send(new TestMail());
                
                $this->info("✅ メーラー {$mailer} でメール送信が完了しました。");
                $this->info("📧 送信先: {$email}");
                $this->info("📧 送信者: contact-jiyugaoka@lme-order.net");
                $this->info("📧 件名: SMTP動作確認テスト");
                $this->info("📧 メールボックスを確認してください。");
                $this->info("📧 スパムフォルダも確認してください。");
                
                $success = true;
                break;
            } catch (\Exception $e) {
                $this->warn("❌ メーラー {$mailer} で送信失敗: " . $e->getMessage());
                continue;
            }
        }
        
        if (!$success) {
            $this->error("❌ すべてのメーラーで送信に失敗しました。");
            $this->error("SMTP設定を確認してください。");
            return 1;
        }
        
        return 0;
    }
} 