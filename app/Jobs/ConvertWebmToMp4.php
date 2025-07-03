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
        $webmDir = dirname($this->webmPath) . '/webm';
        $mp4Dir = dirname($this->mp4Path) . '/mp4';

        if (!is_dir($webmDir)) {
            mkdir($webmDir, 0777, true);
        }
        if (!is_dir($mp4Dir)) {
            mkdir($mp4Dir, 0777, true);
        }

        $webmFileName = basename($this->webmPath);
        $newWebmPath = $webmDir . '/' . $webmFileName;
        if (realpath($this->webmPath) !== realpath($newWebmPath)) {
            rename($this->webmPath, $newWebmPath);
            $this->webmPath = $newWebmPath;
        }

        $mp4FileName = basename($this->mp4Path);
        $mp4OutputPath = $mp4Dir . '/' . preg_replace('/\.webm$/i', '.mp4', $mp4FileName);

        $ffmpegCmd = 'ffmpeg -y -i "' . $this->webmPath . '" -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 128k "' . $mp4OutputPath . '" 2>&1';

        try {
            exec($ffmpegCmd, $output, $returnVar);

            if ($returnVar !== 0) {
                \Log::error('FFmpeg failed', [
                    'cmd' => $ffmpegCmd,
                    'output' => $output,
                    'exit_code' => $returnVar,
                ]);
                throw new \Exception('FFmpeg conversion failed');
            }
        } finally {
            // Always attempt to delete the webm file, even if conversion fails
            if (file_exists($this->webmPath)) {
                @unlink($this->webmPath);
            }
        }
    }
}