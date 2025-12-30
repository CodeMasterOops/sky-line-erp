<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class AddTableColumn extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-table-column';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('fiscal_year_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $this->output->success('Column added');
    }
}
