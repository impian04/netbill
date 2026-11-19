<?php

namespace App\Http\Controllers;

use App\Models\WorkOrderPhoto;
use App\Services\PrivateFileService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WorkOrderPhotoController extends Controller
{
    public function stream(WorkOrderPhoto $photo): BinaryFileResponse
    {
        $user = auth()->user();
        if (! $user) {
            abort(404);
        }

        $workOrder = $photo->workOrder;
        if (! $workOrder) {
            abort(404);
        }

        // Otorisasi hak akses
        if ($user->isSuperAdmin()) {
            return PrivateFileService::streamWorkOrderPhoto($photo->path);
        }

        if ($user->isAdmin() && (int) $user->pop_id === (int) $workOrder->pop_id) {
            return PrivateFileService::streamWorkOrderPhoto($photo->path);
        }

        if ($user->isTechnician() && (int) $user->id === (int) $workOrder->technician_user_id) {
            return PrivateFileService::streamWorkOrderPhoto($photo->path);
        }

        abort(404);
    }
}

