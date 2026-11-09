<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Exceptions\ApiException;
use App\Http\Requests\Pro\UpdateProProfileRequest;
use App\Models\Professional;
use App\Support\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends ProController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->present($request, $this->me($request)));
    }

    public function update(UpdateProProfileRequest $request): JsonResponse
    {
        if (! $request->user()->can('profile.edit')) {
            throw ApiException::forbidden();
        }
        $pro = $this->me($request);
        $data = $request->validated();
        $changes = [];
        if (array_key_exists('phone', $data)) {
            $changes['phone'] = $data['phone'];
            $request->user()->update(['phone' => $data['phone']]);
        }
        if (array_key_exists('bio', $data)) {
            $changes['bio'] = $data['bio'];
        }
        if (array_key_exists('specialties', $data)) {
            $changes['specialties'] = array_values(array_filter($data['specialties']));
        }
        $pro->update($changes);

        return response()->json($this->present($request, $pro->fresh()));
    }

    private function present(Request $request, Professional $pro): array
    {
        return [
            'staffId' => $pro->id,
            'firstName' => $pro->first_name,
            'lastName' => $pro->last_name,
            'email' => $request->user()->email,
            'phone' => $pro->phone,
            'photo' => Media::url($pro->photo),
            'jobTitle' => $pro->job_title,
            'bio' => $pro->bio,
            'specialties' => $pro->specialties ?? [],
            'status' => $pro->is_active ? 'active' : 'inactive',
            'editableFields' => $request->user()->can('profile.edit') ? ['phone', 'bio', 'specialties', 'photo'] : [],
        ];
    }
}
