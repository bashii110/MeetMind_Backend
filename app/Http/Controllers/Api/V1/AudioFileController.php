<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AudioFile\InitAudioUploadRequest;
use App\Http\Requests\AudioFile\StoreAudioChunkRequest;
use App\Http\Resources\AudioFileResource;
use App\Models\AudioFile;
use App\Models\Meeting;
use App\Services\AudioUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 3: capture + reliably upload meeting audio. See
 * App\Services\AudioUploadService for the chunking/resume protocol.
 */
class AudioFileController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AudioUploadService $uploads) {}

    public function init(InitAudioUploadRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $audioFile = $this->uploads->init($meeting, $request->user(), $request->validated());

        return $this->success(new AudioFileResource($audioFile), 'Upload session created.', 201);
    }

    public function storeChunk(StoreAudioChunkRequest $request, AudioFile $audioFile): JsonResponse
    {
        $this->authorize('view', $audioFile->meeting);

        $progress = $this->uploads->storeChunk(
            $audioFile,
            $request->integer('chunk_index'),
            $request->file('chunk'),
        );

        return $this->success($progress, 'Chunk received.');
    }

    public function status(Request $request, AudioFile $audioFile): JsonResponse
    {
        $this->authorize('view', $audioFile->meeting);

        return $this->success($this->uploads->status($audioFile));
    }

    public function complete(Request $request, AudioFile $audioFile): JsonResponse
    {
        $this->authorize('view', $audioFile->meeting);

        $audioFile = $this->uploads->complete($audioFile);

        return $this->success(new AudioFileResource($audioFile), 'Upload complete.');
    }
}
