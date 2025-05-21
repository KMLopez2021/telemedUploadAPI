<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ConvertWebmToMp4;

class ApiController extends Controller
{
    public function itWorks()
    {

        return response()->json(['message' => 'It works!']);
    }


    // public function saveScreenRecord(Request $request)
    // {
    //     $request->validate([
    //         'video' => 'required|file',
    //         'fileName' => 'required|string',
    //         'chunkIndex' => 'required|integer',
    //         'totalChunks' => 'required|integer',
    //     ]);

    //     $fileName = $request->input('fileName');
    //     $chunkIndex = $request->input('chunkIndex');
    //     $totalChunks = $request->input('totalChunks');

    //     // Use a unique temp directory per upload session
    //     $tempDir = storage_path('app/chunks/' . md5($fileName));
    //     if (!is_dir($tempDir)) {
    //         mkdir($tempDir, 0777, true);
    //     }

    //     // Save the chunk atomically
    //     $chunkPath = $tempDir . "/chunk_" . $chunkIndex;
    //     $request->file('video')->move($tempDir, "chunk_" . $chunkIndex);

    //     // Use a lock file to prevent race conditions during assembly
    //     $lockFile = $tempDir . '/.lock';
    //     $allChunksUploaded = true;
    //     for ($i = 0; $i < $totalChunks; $i++) {
    //         if (!file_exists($tempDir . "/chunk_" . $i)) {
    //             $allChunksUploaded = false;
    //             break;
    //         }
    //     }

    //     if ($allChunksUploaded) {
    //         $fp = fopen($lockFile, 'c+');
    //         if (flock($fp, LOCK_EX | LOCK_NB)) {
    //             // Double-check all chunks exist (in case of race)
    //             $missing = false;
    //             for ($i = 0; $i < $totalChunks; $i++) {
    //                 if (!file_exists($tempDir . "/chunk_" . $i)) {
    //                     $missing = true;
    //                     break;
    //                 }
    //             }
    //             if (!$missing) {
    //                 $finalPath = storage_path('app/public/screen_recordings/' . $fileName);
    //                 if (!is_dir(dirname($finalPath))) {
    //                     mkdir(dirname($finalPath), 0777, true);
    //                 }

    //                 $output = fopen($finalPath, 'wb');
    //                 for ($i = 0; $i < $totalChunks; $i++) {
    //                     $chunk = file_get_contents($tempDir . "/chunk_" . $i);
    //                     fwrite($output, $chunk);
    //                 }
    //                 fclose($output);

    //                 // Clean up chunks and lock
    //                 array_map('unlink', glob($tempDir . "/chunk_*"));
    //                 flock($fp, LOCK_UN);
    //                 fclose($fp);
    //                 unlink($lockFile);
    //                 rmdir($tempDir);

    //                 return response()->json(['message' => 'Upload complete', 'path' => 'storage/screen_recordings/' . $fileName]);
    //             }
    //             flock($fp, LOCK_UN);
    //         } else {
    //             fclose($fp);
    //             // Another process is assembling, just return chunk uploaded
    //         }
    //     }

    //     return response()->json(['message' => 'Chunk uploaded'], 200);
    // }

    public function saveConvert(Request $request)
    {
        $request->validate([
            'video' => 'required|file',
            'fileName' => 'required|string',
            'chunkIndex' => 'required|integer',
            'totalChunks' => 'required|integer',
        ]);

        // Sanitize fileName to prevent directory traversal
        $fileName = basename($request->input('fileName'));
        $chunkIndex = $request->input('chunkIndex');
        $totalChunks = $request->input('totalChunks');

        // Use a unique temp directory per upload session
        $tempDir = storage_path('app/chunks/' . md5($fileName));
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Save the chunk atomically
        $chunkPath = $tempDir . "/chunk_" . $chunkIndex;
        $request->file('video')->move($tempDir, "chunk_" . $chunkIndex);

        // Use a lock file to prevent race conditions during assembly
        $lockFile = $tempDir . '/.lock';
        $allChunksUploaded = true;
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!file_exists($tempDir . "/chunk_" . $i)) {
                $allChunksUploaded = false;
                break;
            }
        }

        if ($allChunksUploaded) {
            $fp = fopen($lockFile, 'c+');
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                // Double-check all chunks exist (in case of race)
                $missing = false;
                for ($i = 0; $i < $totalChunks; $i++) {
                    if (!file_exists($tempDir . "/chunk_" . $i)) {
                        $missing = true;
                        break;
                    }
                }
                if (!$missing) {
                    $finalPath = storage_path('app/public/screen_recordings/' . $fileName);
                    if (!is_dir(dirname($finalPath))) {
                        mkdir(dirname($finalPath), 0777, true);
                    }

                    $output = fopen($finalPath, 'wb');
                    for ($i = 0; $i < $totalChunks; $i++) {
                        $chunk = file_get_contents($tempDir . "/chunk_" . $i);
                        fwrite($output, $chunk);
                    }
                    fclose($output);

                    // Clean up chunks and lock
                    array_map('unlink', glob($tempDir . "/chunk_*"));
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    unlink($lockFile);
                    rmdir($tempDir);

                    // Dispatch FFmpeg conversion job to the queue
                    $mp4FileName = preg_replace('/\.webm$/i', '.mp4', $fileName);
                    $finalMp4Path = storage_path('app/public/screen_recordings/' . $mp4FileName);
                    dispatch(new ConvertWebmToMp4($finalPath, $finalMp4Path));

                    return response()->json([
                        'message' => 'Upload complete',
                        'path' => 'storage/screen_recordings/' . $fileName // returns webm path immediately
                    ]);
                }
                flock($fp, LOCK_UN);
            } else {
                fclose($fp);
                // Another process is assembling, just return chunk uploaded
            }
        }

        return response()->json(['message' => 'Chunk uploaded'], 200);
    }
}
