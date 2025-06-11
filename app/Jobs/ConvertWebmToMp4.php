<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ConvertWebmToMp4 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $webmPath;
    protected $mp4Path;

    public function __construct($webmPath, $mp4Path)
    {
        $this->webmPath = $webmPath;
        $this->mp4Path = $mp4Path;
    }

    public function handle()
    {
       $ffmpegCmd = 'ffmpeg -y -i "' . $this->webmPath . '" -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 128k "' . $this->mp4Path . '" 2>&1';
        exec($ffmpegCmd, $output, $returnVar);
       // Delete the webm file only if mp4 was created
        if (file_exists($this->mp4Path) && file_exists($this->webmPath)) {
            @unlink($this->webmPath);
        }
    }
}