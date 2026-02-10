<?php

namespace App\Console\Commands;

use App\Models\CutiBalance;
use App\Models\Pegawai;
use Illuminate\Console\Command;
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

        $total = Pegawai::count();
        $count = 0;
        $this->output->progressStart($total);

        Pegawai::chunk(100, function ($pegawaiChunk) use ($year, &$count) {
            try {
                $uuids = $pegawaiChunk->pluck('uuid')->toArray();
                CutiBalance::bulkCheckAndUpdateBalance($uuids, $year);

                $count += count($uuids);
                $this->output->progressAdvance(count($uuids));
            } catch (\Exception $e) {
                Log::error('Error updating cuti balances for chunk: '.$e->getMessage());
                $this->error('Error updating balances for chunk: '.$e->getMessage());
            }
        });

        $this->output->progressFinish();
        $this->info("Successfully updated $count cuti balances for year $year");
    }
}
