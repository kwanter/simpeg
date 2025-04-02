<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pegawai;
use App\Models\CutiBalance;
use Illuminate\Support\Facades\Log;

class UpdateCutiBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cuti:update-balances {year? : The year to update balances for (defaults to current year)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update cuti balances for all employees, carrying over remaining days from previous year';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->argument('year') ?? date('Y');
        $this->info("Updating cuti balances for year: $year");

        $pegawai = Pegawai::all();
        $count = 0;
        $this->output->progressStart(count($pegawai));

        foreach ($pegawai as $p) {
            try {
                CutiBalance::checkAndUpdateBalance($p->uuid, $year);
                $count++;
                $this->output->progressAdvance();
            } catch (\Exception $e) {
                Log::error("Error updating cuti balance for pegawai {$p->nama} ({$p->nip}): " . $e->getMessage());
                $this->error("Error updating balance for {$p->nama}: " . $e->getMessage());
            }
        }

        $this->output->progressFinish();
        $this->info("Successfully updated $count cuti balances for year $year");
    }
}
