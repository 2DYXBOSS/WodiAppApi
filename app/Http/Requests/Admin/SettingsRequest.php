<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Http\Requests\ApiRequest;

class SettingsRequest extends ApiRequest
{
    public function rules(): array
    {
        $methods = implode(',', array_column(PaymentMethod::cases(), 'value'));

        return [
            'general' => ['sometimes', 'array'],
            'general.name' => ['sometimes', 'required', 'string', 'max:120'],
            'general.description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'general.tagline' => ['sometimes', 'nullable', 'string', 'max:200'],
            'general.address' => ['sometimes', 'nullable', 'string', 'max:300'],
            'general.phone' => ['sometimes', 'nullable', 'string', self::PHONE],
            'general.email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'general.whatsapp' => ['sometimes', 'nullable', 'string', self::PHONE],
            'general.logoUrl' => ['sometimes', 'nullable', 'string', 'max:500'],

            'hours' => ['sometimes', 'array', 'size:7'],
            'hours.*.isoDay' => ['required', 'integer', 'between:1,7', 'distinct'],
            'hours.*.closed' => ['required', 'boolean'],
            'hours.*.open' => ['nullable', 'required_if:hours.*.closed,false', self::TIME],
            'hours.*.close' => ['nullable', 'required_if:hours.*.closed,false', self::TIME],

            'exceptionalDays' => ['sometimes', 'array', 'max:366'],
            'exceptionalDays.*.date' => ['required', self::DATE, 'distinct'],
            'exceptionalDays.*.label' => ['required', 'string', 'max:150'],
            'exceptionalDays.*.closed' => ['required', 'boolean'],

            'booking' => ['sometimes', 'array'],
            'booking.minNoticeHours' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'booking.cancellationNoticeHours' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'booking.cancellationPolicy' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'booking.autoConfirm' => ['sometimes', 'boolean'],
            'booking.slotStepMinutes' => ['sometimes', 'integer', 'in:5,10,15,20,30,45,60'],
            'booking.depositPercent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'booking.allowAnyStaff' => ['sometimes', 'boolean'],
            'booking.bookingWindowDays' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'booking.paymentMethods' => ['sometimes', 'array', 'min:1'],
            'booking.paymentMethods.*' => ['in:'.$methods, 'distinct'],
            'booking.platformUrl' => ['sometimes', 'nullable', 'url', 'max:300'],

            'notifications' => ['sometimes', 'array'],
            'notifications.channels.email' => ['sometimes', 'boolean'],
            'notifications.channels.whatsapp' => ['sometimes', 'boolean'],
            'notifications.events.newReservation' => ['sometimes', 'boolean'],
            'notifications.events.cancellation' => ['sometimes', 'boolean'],
            'notifications.events.payment' => ['sometimes', 'boolean'],
            'notifications.events.newClient' => ['sometimes', 'boolean'],

            'loyalty' => ['sometimes', 'array'],
            'loyalty.vipBenefits' => ['sometimes', 'array', 'max:20'],
            'loyalty.vipBenefits.*' => ['string', 'max:200'],

            'addressDetails' => ['sometimes', 'array'],
            'addressDetails.street' => ['nullable', 'string', 'max:200'],
            'addressDetails.district' => ['nullable', 'string', 'max:120'],
            'addressDetails.city' => ['nullable', 'string', 'max:120'],
            'addressDetails.country' => ['nullable', 'string', 'max:120'],
            'addressDetails.access' => ['nullable', 'string', 'max:300'],
            'addressDetails.geo' => ['nullable', 'array'],
            'addressDetails.geo.lat' => ['required_with:addressDetails.geo', 'numeric', 'between:-90,90'],
            'addressDetails.geo.lng' => ['required_with:addressDetails.geo', 'numeric', 'between:-180,180'],

            'socials' => ['sometimes', 'array'],
            'socials.instagram' => ['nullable', 'url', 'max:300'],
            'socials.facebook' => ['nullable', 'url', 'max:300'],
            'socials.tiktok' => ['nullable', 'url', 'max:300'],
            'apps' => ['sometimes', 'array'],
            'apps.appStore' => ['nullable', 'url', 'max:300'],
            'apps.playStore' => ['nullable', 'url', 'max:300'],
            'legal' => ['sometimes', 'array'],
            'legal.*' => ['nullable', 'string', 'max:300'],
        ];
    }
}
