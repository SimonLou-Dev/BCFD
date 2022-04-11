<?php

namespace App\Jobs;

use App\Models\TestPoudre;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcesTestPoudrePDFGenerator implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    protected $test;
    protected $path;

    /**
     * Create a new job instance.
     *
     * @param TestPoudre $test
     * @param string $path
     */
    public function __construct(TestPoudre $test, string $path)
    {
        $this->test = $test;
        $this->path = $path;

        $this->onQueue('pdfgeneration');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = $this->test->GetPersonnel;
        $test = $this->test;
        $path = $this->path;

        $pdf = Pdf::loadView('pdf.TDP',['test'=>$test, 'user'=>$user]);

        $pdf->save($path);

    }
}
