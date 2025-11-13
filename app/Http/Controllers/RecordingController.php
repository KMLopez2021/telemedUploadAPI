<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\File;

class RecordingController extends Controller
{
    public function startRecording(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
            'uid'     => 'required|string',
        ]);

        $channel = $request->channel;
        $uid     = $request->uid;

        // Directory to save recordings
        $recordDir = storage_path("app/recordings/{$channel}");
        if (!is_dir($recordDir) && !mkdir($recordDir, 0777, true) && !is_dir($recordDir)) {
            Log::error("Failed to create recording directory: {$recordDir}");
            return response()->json([
                'status' => 'error',
                'message' => "Failed to create recording directory"
            ], 500);
        }

        // Path to recorder executable
        $recorderPath = base_path('agora_rtc_sdk/example/recorder/build/sample_recorder');
        if (!file_exists($recorderPath)) {
            Log::error("Recorder executable not found: {$recorderPath}");
            return response()->json([
                'status' => 'error',
                'message' => "Recorder executable not found"
            ], 500);
        }

        // JSON config file path
        $jsonPath = $recordDir . "/recorder.json";

        // Generate JSON config dynamically
         $config = [
            'appId' => '0fc02f6b7ce04fbcb1991d71df2dbe0d',
            'token' => "1f6d29c353cb4c25a8527bcbf8ff7b4e", // empty if not using token
            'channelName' => $channel,
            'useStringUid' => false,
            'useCloudProxy' => false,
            'userId' => $uid,
            'subAllAudio' => true,
            'subAllVideo' => true,
            'subStreamType' => 'high',
            'isMix' => true,
            'layoutMode' => 'bestfit',
            'recorderStreamType' => 'both',
            'recorderPath' => $recordDir . "/{$channel}.mp4",
            'audio' => [
                'sampleRate' => 16000,
                'numOfChannels' => 1
            ],
            'video' => [
                'width' => 1920,
                'height' => 1080,
                'fps' => 15
            ]
        ];


        File::put($jsonPath, json_encode($config, JSON_PRETTY_PRINT));

        // Start recorder process
        $process = new Process([
            $recorderPath,
            $jsonPath
        ], base_path('agora_rtc_sdk/example/recorder/build'), [
            'LD_LIBRARY_PATH' => base_path('agora_rtc_sdk/agora_sdk')
        ]);

        $process->setTimeout(0); // run indefinitely

        try {
            $process->start();

            // Optional: log output asynchronously
            $process->wait(function ($type, $buffer) use ($channel) {
                if ($type === Process::OUT) {
                    Log::info("[Recorder stdout - {$channel}] " . $buffer);
                } else {
                    Log::error("[Recorder stderr - {$channel}] " . $buffer);
                }
            });

            Log::info("Recorder started for channel {$channel}, UID {$uid}");

        } catch (\Exception $e) {
            Log::error("Recorder failed to start: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Recorder failed to start: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Recorder started successfully',
        ]);
    }

    public function stopRecording(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
        ]);

        $channel = $request->channel;

        // Find running recorder processes for this channel
        $grepCommand = "ps aux | grep sample_recorder | grep '{$channel}' | grep -v grep | awk '{print $2}'";
        exec($grepCommand, $output);

        if (empty($output)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No recorder process found for this channel'
            ], 404);
        }

        // Kill all recorder processes for this channel
        foreach ($output as $pid) {
            exec("kill -9 {$pid}");
            Log::info("Stopped recorder process {$pid} for channel {$channel}");
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Recorder stopped successfully',
        ]);
    }
}
