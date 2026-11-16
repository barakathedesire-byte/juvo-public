<?php

namespace App\Console\Commands;

use App\Services\Pesapal\PesapalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RegisterPesapalIpn extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pesapal:register-ipn 
                            {--url= : The IPN callback URL (defaults to route pesapal.webhook)}
                            {--type=POST : The IPN notification type (GET|POST)}
                            {--save-env : Update the .env file with the notification_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register an IPN URL with Pesapal for webhook notifications';

    public function handle(PesapalService $pesapal): int
    {
        $url = $this->option('url') ?? route('pesapal.webhook');
        $type = strtoupper($this->option('type'));

        if (! in_array($type, ['GET', 'POST'])) {
            $this->error('Invalid IPN type. Must be GET or POST.');

            return Command::FAILURE;
        }

        $this->info("Registering IPN URL: {$url}");
        $this->info("Notification type: {$type}");

        try {
            $response = $pesapal->registerIpn($url, $type);
        } catch (\Exception $e) {
            $this->error('Failed to register IPN: '.$e->getMessage());

            return Command::FAILURE;
        }

        $notificationId = $response['ipn_id'] ?? $response['notification_id'] ?? null;

        if (! $notificationId) {
            $this->error('No notification_id returned in response: '.json_encode($response));

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('IPN Registration Successful!');
        $this->table(['Property', 'Value'], [
            ['Notification ID', $notificationId],
            ['URL', $url],
            ['Type', $type],
            ['Environment', config('pesapal.environment')],
        ]);

        if ($this->option('save-env')) {
            $this->updateEnvFile('PESAPAL_IPN_ID', $notificationId);
            $this->info('Updated .env file with PESAPAL_IPN_ID='.$notificationId);
        } else {
            $this->warn('To persist this ID, run the command with --save-env or manually add to .env:');
            $this->line('PESAPAL_IPN_ID='.$notificationId);
        }

        return Command::SUCCESS;
    }

    protected function updateEnvFile(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $content = File::exists($envPath) ? File::get($envPath) : '';

        if (Str::contains($content, $key.'=')) {
            $content = preg_replace(
                '/^'.$key.'=.*/m',
                $key.'='.$value,
                $content
            );
        } else {
            $content .= "\n".$key.'='.$value;
        }

        File::put($envPath, $content);
    }
}
