<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateProfileRequest;
use App\Http\Resources\SessionUserResource;
use App\Models\Customer;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /** `PATCH /me` : seuls prénom, nom, téléphone et date de naissance sont modifiables. */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $customer = $user->customer;

        if ($customer && Customer::query()->where('phone', $data['phone'])->whereKeyNot($customer->id)->exists()) {
            throw ApiException::unprocessable('Ce numéro est déjà utilisé.', ['phone' => 'Numéro déjà utilisé.']);
        }

        DB::transaction(function () use ($user, $data, $customer) {
            $user->update([
                'first_name' => $data['firstName'], 'last_name' => $data['lastName'],
                'phone' => $data['phone'], 'birth_date' => $data['birthDate'] ?? null,
            ]);
            $customer?->update([
                'first_name' => $data['firstName'], 'last_name' => $data['lastName'],
                'phone' => $data['phone'], 'birth_date' => $data['birthDate'] ?? null,
            ]);
        });

        return response()->json(new SessionUserResource($user->fresh()));
    }

    /** `POST /me/avatar` (multipart `file`). */
    public function avatar(Request $request, MediaService $media): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.config('wodi.media.max_kb'), 'mimetypes:'.implode(',', config('wodi.media.mimes'))]]);
        $user = $request->user();
        if ($user->isBarber() && ! $user->can('profile.edit')) {
            throw ApiException::forbidden();
        }
        $url = $media->store($request->file('file'), 'avatars');
        $media->deleteIfManaged($user->avatar_path);
        $user->update(['avatar_path' => $url]);
        if ($user->isBarber() && $user->professional) {
            $media->deleteIfManaged($user->professional->photo);
            $user->professional->update(['photo' => $url]);
        }

        return response()->json(new SessionUserResource($user->fresh()));
    }
}
