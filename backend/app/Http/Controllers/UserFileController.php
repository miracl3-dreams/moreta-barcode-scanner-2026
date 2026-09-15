<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveUserFileAccessRequest;
use App\Http\Requests\StoreUserFileRequest;
use App\Http\Requests\UpdateUserFileRequest;
use App\Models\UserFile;
use App\Services\UserFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserFileController extends Controller
{
    public function __construct(private UserFileService $users) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->users->lookups());
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->users->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreUserFileRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return response()->json($user->toListArray(), 201);
    }

    public function show(UserFile $userFile): JsonResponse
    {
        return response()->json($userFile->toFormArray());
    }

    public function update(UpdateUserFileRequest $request, UserFile $userFile): JsonResponse
    {
        $user = $this->users->update($userFile, $request->validated());

        return response()->json($user->toListArray());
    }

    public function destroy(UserFile $userFile): JsonResponse
    {
        $this->users->delete($userFile);

        return response()->json(['ok' => true]);
    }

    public function resetLogin(UserFile $userFile): JsonResponse
    {
        $this->users->resetLogin($userFile);

        return response()->json(['ok' => true]);
    }

    public function access(UserFile $userFile): JsonResponse
    {
        return response()->json($this->users->access($userFile));
    }

    public function saveAccess(SaveUserFileAccessRequest $request, UserFile $userFile): JsonResponse
    {
        $this->users->saveAccess($userFile, $request->validated());

        return response()->json(['ok' => true]);
    }

    public function template(): StreamedResponse
    {
        return $this->users->template();
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->users->export(
            trim((string) $request->query('search', '')),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        );
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $path = $request->file('file')?->getRealPath();
        if ($path === false || $path === null) {
            return response()->json(['message' => 'Unable to read uploaded file.'], 422);
        }

        $result = $this->users->import($path);
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
